<?php

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

        if (! $this->verifySignature($gateway, $payload, $headers, $request)) {
            return WebhookResult::failure(
                eventType: 'unknown',
                transactionId: (string) (data_get($payload, 'orderNo') ?? data_get($payload, 'order_no') ?? data_get($payload, 'id') ?? ''),
                errorMessage: "Webhook signature verification failed for gateway [{$gateway}]."
            );
        }

        $result = $gatewayInstance->webhook($request);

        if (! $result->success) {
            // The reference, not the body: a webhook payload carries customer
            // and order detail that has no business sitting in plaintext logs.
            Log::warning("Webhook handling failed for gateway [{$gateway}]: {$result->errorMessage}", [
                'transaction_id' => $result->transactionId,
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
    protected function verifySignature(string $gateway, array $payload, array $headers, ?Request $request = null): bool
    {
        $shouldVerify = (bool) config('payment-gateway.webhook.verify_signature', true);

        if (! $shouldVerify) {
            return true;
        }

        // `$request->headers->all()` hands back every value as a list, so a
        // signature arrived here as ['t=...,v1=...'] and casting it to string
        // produced the literal "Array" — which never verifies. Every Stripe
        // webhook was rejected at this line.
        $normalizedHeaders = collect($headers)
            ->mapWithKeys(fn (mixed $value, mixed $key): array => [
                strtolower((string) $key) => is_array($value) ? ($value[0] ?? null) : $value,
            ])
            ->all();

        if ($gateway === 'stripe') {
            $signature = $normalizedHeaders['stripe-signature'] ?? $normalizedHeaders['stripe_signature'] ?? null;

            if ($signature === null) {
                $signature = $payload['stripe_signature'] ?? null;
            }

            if (blank($signature)) {
                $webhookSecret = $this->resolveStripeSecret();

                if (blank($webhookSecret)) {
                    Log::warning("Webhook signature verification skipped for gateway [{$gateway}]: webhook secret is not configured.");

                    return false;
                }

                return false;
            }

            $secret = $this->resolveStripeSecret();

            if (blank($secret)) {
                Log::warning("Stripe webhook secret is not configured for gateway [{$gateway}].");

                return false;
            }

            try {
                // The HMAC covers the exact bytes Stripe sent. Re-encoding the
                // decoded array is not those bytes — escaping and spacing differ
                // — so this compared a signature against a payload Stripe never
                // signed and failed for every real webhook.
                $rawPayload = $request?->getContent()
                    ?: ($payload['_raw'] ?? json_encode($payload, JSON_THROW_ON_ERROR));
                Webhook::constructEvent($rawPayload, (string) $signature, $secret);

                return true;
            } catch (Throwable) {
                return false;
            }
        }

        if ($gateway === 'himalayan') {
            // Deliberate, not an oversight. PACO does not sign its notification,
            // and it does not need to: HimalayanBankGateway::webhook() reads only
            // the order number from the body and then calls verify(), a signed
            // JOSE inquiry, taking the outcome from the bank's own response. A
            // forged payload cannot assert that a payment succeeded — the worst
            // it can do is make us ask about an order number, which is why the
            // route is rate limited.
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

        $expected = hash_hmac(
            'sha256',
            $request?->getContent() ?: ($payload['_raw'] ?? json_encode($payload, JSON_THROW_ON_ERROR)),
            $secret
        );

        return hash_equals($expected, (string) $provided);
    }

    private function resolveStripeSecret(): string
    {
        try {
            if (app()->bound(GatewayResolver::class)) {
                $gateway = app(GatewayResolver::class)->getGatewayModel('stripe');
                if ($gateway && filled($gateway->getStripeWebhookSecret())) {
                    return (string) $gateway->getStripeWebhookSecret();
                }
            }
        } catch (Throwable) {
        }

        return (string) config('payment-gateway.gateways.stripe.webhook_secret', config('payment-gateway.webhook.secret', ''));
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

        $extra = [];

        if ($result->amount > 0 && $payment->amount_cents === 0) {
            $extra['amount_cents'] = (int) round($result->amount * 100);
        }

        // Only when we do not already know it. The webhook used to overwrite the
        // currency on every delivery, and an unrecognised code falls back to the
        // gateway's default — so a USD payment could be relabelled NPR by a
        // message that told us nothing new.
        if (filled($result->currency) && blank($payment->currency)) {
            $extra['currency'] = strtoupper($result->currency);
        }

        // Through the locked transition. A webhook redelivered after a refund
        // used to map back to `succeeded`, move the status, and fire fulfilment a
        // second time on money that had already been given back.
        $payment->settleTo($newStatus, $extra);
    }
}
