<?php

namespace Kreetancraft\PaymentGateway\Gateways;

use Exception;
use Illuminate\Http\Request;
use Kreetancraft\PaymentGateway\Data\PaymentResult;
use Kreetancraft\PaymentGateway\Data\RefundResult;
use Kreetancraft\PaymentGateway\Data\VerificationResult;
use Kreetancraft\PaymentGateway\Data\WebhookResult;
use Kreetancraft\PaymentGateway\Models\Gateway;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;
use UnexpectedValueException;

/**
 * Stripe, through Stripe-hosted Checkout.
 *
 * The buyer is redirected to Stripe's own page and comes back to `success_url`.
 * That is deliberate: this package has no card form, and it should not have one
 * — hosting the field ourselves would drag the application into PCI scope for
 * no benefit.
 *
 * It also closes a hole. The previous implementation created a PaymentIntent and
 * returned no redirect URL, and the checkout screen treats "success with no
 * redirect" as done — so the buyer was sent to the success page the moment the
 * intent was *created*, holding a reference to a payment sitting in
 * `requires_payment_method`. Nobody had entered a card and no money had moved.
 *
 * Fulfilment is driven from the webhook, never from the return page: a buyer can
 * pay and then lose their connection before the redirect lands.
 */
class StripeGateway extends AbstractGateway
{
    private StripeClient $client;

    public function __construct(Gateway $gateway, ?StripeClient $client = null)
    {
        parent::__construct($gateway);
        // The version was hardcoded to a string that does not match any
        // documented release. An unknown API version fails every call, and it is
        // not the sort of thing to guess at — pin it in config, per environment,
        // and verify it against the account before shipping.
        $this->client = $client ?? new StripeClient(array_filter([
            'api_key' => (string) $this->gateway->getStripeSecretKey(),
            'stripe_version' => config('payment-gateway.gateways.stripe.api_version'),
        ]));
    }

    public function charge(array $data): PaymentResult
    {
        $reference = (string) ($data['order_reference'] ?? $data['reference'] ?? '');

        try {
            $params = [
                'mode' => 'payment',
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => strtolower((string) $data['currency']),
                        'unit_amount' => (int) $data['amount_cents'],
                        'product_data' => [
                            'name' => $this->productName($data),
                        ],
                    ],
                ]],
                'success_url' => $this->appendSessionPlaceholder(
                    $this->resolveRedirectUrl('success', ['reference' => $reference], $data['return_url'] ?? null)
                ),
                'cancel_url' => $this->resolveRedirectUrl('cancel', ['reference' => $reference]),
            ];

            // `payment_method_types` is deliberately absent. Omitting it turns on
            // dynamic payment methods, so what the buyer is offered follows from
            // the currency, amount and country and is managed from the Dashboard
            // — adding a method later needs no code change here.

            if (filled($data['customer_email'] ?? null)) {
                $params['customer_email'] = (string) $data['customer_email'];
            }

            if ($reference !== '') {
                $params['client_reference_id'] = $reference;
            }

            if (filled($data['metadata'] ?? null)) {
                $params['metadata'] = $data['metadata'];
                // Repeated onto the PaymentIntent: the session is a checkout
                // artefact and expires, while the intent is what shows up later
                // in disputes and payouts.
                $params['payment_intent_data'] = ['metadata' => $data['metadata']];
            }

            $session = $this->client->checkout->sessions->create($params, $this->idempotencyOptions($data));

            return PaymentResult::success(
                orderReference: $session->id,
                redirectUrl: $session->url,
                checkoutData: json_encode([
                    'session_id' => $session->id,
                    'expires_at' => $session->expires_at ?? null,
                ])
            );
        } catch (ApiErrorException $e) {
            return PaymentResult::failure(
                orderReference: $reference,
                errorMessage: $e->getMessage(),
                errorCode: $e->getStripeCode() ?? (string) $e->getCode()
            );
        }
    }

    /**
     * Stripe substitutes the real id on the way out. It must not be url-encoded,
     * so it is appended rather than passed through `http_build_query`.
     */
    private function appendSessionPlaceholder(string $url): string
    {
        return $url.(str_contains($url, '?') ? '&' : '?').'session_id={CHECKOUT_SESSION_ID}';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function productName(array $data): string
    {
        $name = trim((string) ($data['description'] ?? ''));

        // Stripe rejects an empty product name, and the description is free text
        // that a caller may well not have set.
        return $name === '' ? 'Payment' : $name;
    }

    /**
     * Stripe's own idempotency, on top of the local check.
     *
     * The database lookup in ChargePaymentAction only stops a second row being
     * written here; it does nothing at Stripe's end, so a retried request that
     * got past it could still create a second session and charge twice.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function idempotencyOptions(array $data): array
    {
        // Prefer the key the caller computed: it covers the payable, the
        // gateway, the amount and the attempt. The reference seed alone is the
        // invoice number, which never changes — so applying a coupon and paying
        // again reached Stripe with the first request's key and a different
        // amount, and Stripe refused it outright.
        $key = (string) ($data['idempotency_key'] ?? '');

        if ($key !== '') {
            return ['idempotency_key' => 'charge:'.$key];
        }

        $seed = (string) ($data['reference_seed'] ?? $data['order_reference'] ?? '');

        return $seed === '' ? [] : ['idempotency_key' => 'charge:'.$seed];
    }

    public function refund(string $transactionId, float $amount): RefundResult
    {
        try {
            $intentId = $this->resolvePaymentIntentId($transactionId);

            if ($intentId === null) {
                return RefundResult::failure(
                    transactionId: $transactionId,
                    amount: $amount,
                    errorMessage: 'That checkout session has no payment to refund.',
                    errorCode: 'no_payment_intent'
                );
            }

            $refund = $this->client->refunds->create([
                'payment_intent' => $intentId,
                'amount' => (int) round($amount * 100),
            ]);

            return RefundResult::success(
                transactionId: $transactionId,
                amount: $amount,
                refundId: $refund->id
            );
        } catch (ApiErrorException $e) {
            return RefundResult::failure(
                transactionId: $transactionId,
                amount: $amount,
                errorMessage: $e->getMessage(),
                errorCode: $e->getCode()
            );
        }
    }

    /**
     * A payment is recorded against whatever the charge handed back. That is now
     * a session id, but rows written by the old PaymentIntent flow are still in
     * the table and still have to verify and refund.
     *
     * @throws ApiErrorException
     */
    private function resolvePaymentIntentId(string $transactionId): ?string
    {
        if (! str_starts_with($transactionId, 'cs_')) {
            return $transactionId;
        }

        $session = $this->client->checkout->sessions->retrieve($transactionId);

        $intent = $session->payment_intent ?? null;

        return is_string($intent) ? $intent : ($intent->id ?? null);
    }

    public function verify(array $data): VerificationResult
    {
        $id = (string) ($data['session_id']
            ?? $data['payment_intent_id']
            ?? $data['transaction_id']
            ?? $data['reference']
            ?? $data['order']
            ?? $data['orderNo']
            ?? $data['order_no']
            ?? '');

        if ($id === '') {
            return VerificationResult::failure('', 'Missing session or payment intent id for verification.');
        }

        try {
            if (str_starts_with($id, 'cs_')) {
                $session = $this->client->checkout->sessions->retrieve($id);

                $status = $this->statusForSession(
                    (string) ($session->payment_status ?? ''),
                    (string) ($session->status ?? '')
                );

                return VerificationResult::success(
                    transactionId: $session->id,
                    status: $status,
                    amount: ($session->amount_total ?? 0) / 100,
                    currency: strtoupper((string) ($session->currency ?? '')),
                    paidAt: $status === 'succeeded' ? now()->toDateTimeString() : null
                );
            }

            $paymentIntent = $this->client->paymentIntents->retrieve($id);

            return VerificationResult::success(
                transactionId: $paymentIntent->id,
                status: $paymentIntent->status,
                amount: $paymentIntent->amount / 100,
                currency: strtoupper($paymentIntent->currency),
                paidAt: $paymentIntent->status === 'succeeded' ? now()->toDateTimeString() : null
            );
        } catch (ApiErrorException $e) {
            return VerificationResult::failure(
                transactionId: $id,
                errorMessage: $e->getMessage()
            );
        }
    }

    /**
     * `payment_status` is the field to read, not `status`.
     *
     * With a delayed-notification method the session completes while it is still
     * unpaid, and the money arrives — or does not — days later.
     */
    private function statusForSession(string $paymentStatus, string $sessionStatus): string
    {
        if (in_array($paymentStatus, ['paid', 'no_payment_required'], true)) {
            return 'succeeded';
        }

        if ($sessionStatus === 'expired') {
            return 'canceled';
        }

        return 'pending';
    }

    public function webhook(Request $request): WebhookResult
    {
        $secret = $this->gateway->getStripeWebhookSecret();

        if (! $secret) {
            return WebhookResult::failure(
                eventType: 'unknown',
                transactionId: '',
                errorMessage: 'Webhook secret not configured'
            );
        }

        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                $request->header('Stripe-Signature', ''),
                $secret,
            );
        } catch (SignatureVerificationException $e) {
            return WebhookResult::failure(
                eventType: 'unknown',
                transactionId: '',
                errorMessage: "Signature verification failed: {$e->getMessage()}"
            );
        } catch (UnexpectedValueException $e) {
            return WebhookResult::failure(
                eventType: 'unknown',
                transactionId: '',
                errorMessage: "Invalid payload: {$e->getMessage()}"
            );
        }

        try {
            $eventType = (string) $event->type;
            $object = $event->data->object ?? null;

            if (! $object) {
                return WebhookResult::failure($eventType, '', 'No object in webhook payload');
            }

            $status = match ($eventType) {
                // `completed` can arrive while the session is still unpaid, so
                // the decision comes from payment_status rather than from the
                // event name. Fulfilling on the event alone would grant access
                // for payments that later fail, and never fulfil the ones that
                // eventually succeed.
                'checkout.session.completed' => $this->statusForSession(
                    (string) ($object->payment_status ?? ''),
                    (string) ($object->status ?? '')
                ),
                'checkout.session.async_payment_succeeded' => 'succeeded',
                'checkout.session.async_payment_failed' => 'failed',
                'checkout.session.expired' => 'canceled',

                // Kept for payments taken by the previous PaymentIntent flow.
                'payment_intent.succeeded' => 'succeeded',
                'payment_intent.payment_failed' => 'failed',
                'payment_intent.canceled' => 'canceled',
                'payment_intent.requires_action' => 'requires_action',

                default => 'pending',
            };

            $amount = $object->amount_total ?? $object->amount ?? 0;

            return WebhookResult::success(
                eventType: $eventType,
                transactionId: (string) ($object->id ?? ''),
                status: $status,
                amount: $amount / 100,
                currency: strtoupper((string) ($object->currency ?? '')),
            );
        } catch (Exception $e) {
            return WebhookResult::failure(
                eventType: 'unknown',
                transactionId: '',
                errorMessage: $e->getMessage()
            );
        }
    }

    public function supportsCurrency(string $currency): bool
    {
        return in_array(strtoupper($currency), array_map('strtoupper', $this->getSupportedCurrencies()));
    }

    public function checkoutRedirect(): bool
    {
        return true;
    }

    public function getSupportedCurrencies(): array
    {
        return $this->gateway->getSupportedCurrencies();
    }

    public function getCode(): string
    {
        return 'stripe';
    }

    public function getLabel(): string
    {
        return 'Pay with Stripe';
    }

    public function getIcon(): string
    {
        return 'https://js.stripe.com/v3/stripe-logo.svg';
    }
}
