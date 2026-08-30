<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Actions;

use Illuminate\Support\Facades\Log;
use Kreetancraft\PaymentGateway\Contracts\GatewayResolver;
use Kreetancraft\PaymentGateway\Data\WebhookResult;
use Kreetancraft\PaymentGateway\Models\Payment;
use Lorisleiva\Actions\Concerns\AsAction;

class HandleWebhookAction
{
    use AsAction;

    public function __construct(
        protected GatewayResolver $resolver,
    ) {}

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $headers
     */
    public function handle(string $gateway, array $payload, array $headers = []): WebhookResult
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

        if (! $this->verifySignature($gateway, $payload, $headers)) {
            return WebhookResult::failure(
                eventType: 'unknown',
                transactionId: (string) (data_get($payload, 'orderNo') ?? data_get($payload, 'order_no') ?? data_get($payload, 'id') ?? ''),
                errorMessage: "Webhook signature verification failed for gateway [{$gateway}]."
            );
        }

        $result = $gatewayInstance->webhook($payload);

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
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $headers
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
                    return true;
                }

                return false;
            }

            $secret = (string) config('payment-gateway.gateways.stripe.webhook_secret', config('payment-gateway.webhook.secret', ''));

            if (blank($secret)) {
                return true;
            }

            try {
                $rawPayload = json_encode($payload, JSON_THROW_ON_ERROR);
                \Stripe\Webhook::constructEvent($rawPayload, (string) $signature, $secret);

                return true;
            } catch (\Throwable) {
                return false;
            }
        }

        if ($gateway === 'himalayan') {
            return true;
        }

        $secret = (string) config('payment-gateway.webhook.secret', '');

        if (blank($secret)) {
            return true;
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
            'succeeded' => 'succeeded',
            'completed' => 'succeeded',
            'paid' => 'succeeded',
            'success' => 'succeeded',
            'failed' => 'failed',
            'canceled' => 'canceled',
            'cancelled' => 'canceled',
            'pending' => 'pending',
            'requires_action' => 'requires_action',
        ];

        $newStatus = $statusMap[strtolower($result->status)] ?? $result->status;

        $payment->status = $newStatus;

        if (in_array($newStatus, ['succeeded', 'completed', 'paid'], true)) {
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
