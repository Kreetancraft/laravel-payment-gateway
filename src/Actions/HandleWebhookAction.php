<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Actions;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Kreetancraft\PaymentGateway\Contracts\GatewayResolver;
use Kreetancraft\PaymentGateway\Data\WebhookResult;
use Kreetancraft\PaymentGateway\Enums\PaymentStatus;
use Kreetancraft\PaymentGateway\Models\Payment;
use Lorisleiva\Actions\Concerns\AsAction;
use Stripe\Webhook;
use Throwable;

class HandleWebhookAction
{
    use AsAction;

    public function __construct(
        protected GatewayResolver $resolver,
    ) {}

    public function handle(string $gateway, Request|array $request = [], array $headers = [], ?array $payload = null): WebhookResult
    {
        if (blank($gateway)) {
            return WebhookResult::failure(
                eventType: 'unknown',
                transactionId: '',
                errorMessage: 'Gateway code is required.'
            );
        }

        $gatewayInstance = $this->resolver->resolve($gateway);

        if ($gatewayInstance === null) {
            return WebhookResult::failure(
                eventType: 'unknown',
                transactionId: '',
                errorMessage: "Gateway [{$gateway}] is not configured or not enabled."
            );
        }

        if ($payload !== null && empty($request)) {
            $request = $payload;
        }

        if (is_array($request)) {
            $rawPayload = $request;
            $request = Request::create(
                uri: "/payment/webhook/{$gateway}",
                method: 'POST',
                parameters: $rawPayload,
                server: collect($headers)->mapWithKeys(fn (mixed $v, mixed $k): array => [
                    'HTTP_'.strtoupper(str_replace('-', '_', (string) $k)) => $v,
                ])->all(),
                content: json_encode($rawPayload) ?: ''
            );
            $payload = $rawPayload;
        } else {
            $payload = $request->all() ?: (array) json_decode($request->getContent() ?: '[]', true);
            $headers = $request->headers->all();
        }

        if (! $this->verifySignature($gateway, $payload, $headers)) {
            return WebhookResult::failure(
                eventType: 'unknown',
                transactionId: (string) (data_get($payload, 'orderNo') ?? data_get($payload, 'order_no') ?? data_get($payload, 'id') ?? ''),
                errorMessage: "Webhook signature verification failed for gateway [{$gateway}]."
            );
        }

        $result = $gatewayInstance->webhook($request);

        if (! $result->success) {
            Log::warning("Webhook handling failed for gateway [{$gateway}]: {$result->errorMessage}", [
                'payload' => $payload,
            ]);

            return $result;
        }

        $this->updatePaymentStatus($result);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $headers
     */
    protected function verifySignature(string $gateway, array $payload, array $headers): bool
    {
        $shouldVerify = (bool) config('payment-gateway.webhook.verify_signature', true);

        if (! $shouldVerify) {
            return true;
        }

        $normalizedHeaders = collect($headers)
            ->mapWithKeys(fn (mixed $value, mixed $key): array => [strtolower((string) $key) => $value])
            ->all();

        if ($gateway === 'stripe') {
            $signature = $normalizedHeaders['stripe-signature'] ?? $normalizedHeaders['stripe_signature'] ?? null;

            if ($signature === null) {
                $signature = $payload['stripe_signature'] ?? null;
            }

            if (blank($signature)) {
                $webhookSecret = (string) config('payment-gateway.gateways.stripe.webhook_secret', config('payment-gateway.webhook.secret', ''));

                if (blank($webhookSecret)) {
                    Log::warning("Webhook signature verification skipped for gateway [{$gateway}]: webhook secret is not configured.");

                    return false;
                }

                return false;
            }

            $secret = (string) config('payment-gateway.gateways.stripe.webhook_secret', config('payment-gateway.webhook.secret', ''));

            if (blank($secret)) {
                Log::warning("Stripe webhook secret is not configured for gateway [{$gateway}].");

                return false;
            }

            try {
                $rawPayload = json_encode($payload, JSON_THROW_ON_ERROR);
                Webhook::constructEvent($rawPayload, (string) $signature, $secret);

                return true;
            } catch (Throwable) {
                return false;
            }
        }

        if ($gateway === 'himalayan') {
            return true;
        }

        $secret = (string) config('payment-gateway.webhook.secret', '');

        if (blank($secret)) {
            Log::warning("Webhook secret is not configured for gateway [{$gateway}].");

            return false;
        }

        $provided = $normalizedHeaders['x-webhook-signature'] ?? $normalizedHeaders['signature'] ?? $normalizedHeaders['x-signature'] ?? null;

        if (blank($provided)) {
            return false;
        }

        $expected = hash_hmac('sha256', json_encode($payload, JSON_THROW_ON_ERROR), $secret);

        return hash_equals($expected, (string) $provided);
    }

    protected function updatePaymentStatus(WebhookResult $result): void
    {
        if (blank($result->transactionId)) {
            return;
        }

        $payment = Payment::query()->where('gateway_reference', $result->transactionId)->first();

        if ($payment === null) {
            $orderNo = $result->transactionId;

            if (str_contains($orderNo, '-')) {
                $payment = Payment::query()->where('reference', $orderNo)->first();
            }
        }

        if ($payment === null) {
            return;
        }

        $statusMap = [
            'succeeded' => PaymentStatus::Succeeded,
            'completed' => PaymentStatus::Succeeded,
            'paid' => PaymentStatus::Succeeded,
            'success' => PaymentStatus::Succeeded,
            'failed' => PaymentStatus::Failed,
            'canceled' => PaymentStatus::Canceled,
            'cancelled' => PaymentStatus::Canceled,
            'pending' => PaymentStatus::Pending,
            'requires_action' => PaymentStatus::RequiresAction,
        ];

        $newStatus = $statusMap[strtolower((string) $result->status)] ?? PaymentStatus::tryFrom((string) $result->status) ?? PaymentStatus::Pending;

        $payment->status = $newStatus;

        if ($newStatus === PaymentStatus::Succeeded) {
            $payment->paid_at = $payment->paid_at ?? now();
        }

        if (isset($result->amount) && $payment->amount_cents === 0) {
            $payment->amount_cents = (int) round($result->amount * 100);
        }

        if (filled($result->currency)) {
            $payment->currency = strtoupper($result->currency);
        }

        $payment->save();
    }
}
