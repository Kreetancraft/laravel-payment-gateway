<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Kreetancraft\PaymentGateway\Contracts\GatewayResolver;
use Stripe\Webhook;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebhookSignature
{
    public function handle(Request $request, Closure $next, ?string $gateway = null): Response
    {
        $shouldVerify = (bool) config('payment-gateway.webhook.verify_signature', true);

        if (! $shouldVerify) {
            return $next($request);
        }

        $gateway = $gateway ?? (string) $request->route('gateway', '');

        if (blank($gateway)) {
            return $next($request);
        }

        if ($gateway === 'himalayan') {
            return $next($request);
        }

        if ($gateway === 'stripe') {
            $signature = $request->header('stripe-signature') ?? $request->header('Stripe-Signature');

            if (blank($signature)) {
                $webhookSecret = $this->resolveStripeSecret();

                if (blank($webhookSecret)) {
                    return $next($request);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Missing Stripe signature header.',
                ], 400);
            }

            $secret = $this->resolveStripeSecret();

            if (blank($secret)) {
                return $next($request);
            }

            $payload = $request->getContent();

            if (blank($payload)) {
                // Do not re-encode parsed JSON — signature would mismatch; use exactly what Stripe sent
                $payload = $request->getContent();
            }

            try {
                Webhook::constructEvent($payload, (string) $signature, $secret);
            } catch (\Throwable $exception) {
                return response()->json([
                    'success' => false,
                    'message' => 'Webhook signature verification failed: '.$exception->getMessage(),
                ], 400);
            }

            return $next($request);
        }

        $secret = (string) config('payment-gateway.webhook.secret', '');

        if (blank($secret)) {
            return $next($request);
        }

        $provided = $request->header('x-webhook-signature')
            ?? $request->header('signature')
            ?? $request->header('x-signature');

        if (blank($provided)) {
            return response()->json([
                'success' => false,
                'message' => 'Missing webhook signature header.',
            ], 400);
        }

        $payload = $request->getContent();

        $expected = hash_hmac('sha256', $payload, $secret);

        if (! hash_equals($expected, (string) $provided)) {
            return response()->json([
                'success' => false,
                'message' => 'Webhook signature mismatch.',
            ], 400);
        }

        return $next($request);
    }

    private function resolveStripeSecret(): string
    {
        // Prefer DB-encrypted secret so UI and middleware stay in sync
        try {
            if (app()->bound(GatewayResolver::class)) {
                $gateway = app(GatewayResolver::class)->getGatewayModel('stripe');
                if ($gateway && filled($gateway->getStripeWebhookSecret())) {
                    return (string) $gateway->getStripeWebhookSecret();
                }
            }
        } catch (\Throwable) {
        }

        return (string) config('payment-gateway.gateways.stripe.webhook_secret', config('payment-gateway.webhook.secret', ''));
    }
}
