<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Kreetancraft\PaymentGateway\Actions\HandleWebhookAction;
use Kreetancraft\PaymentGateway\Enums\PaymentStatus;
use Kreetancraft\PaymentGateway\Gateways\StripeGateway;
use Kreetancraft\PaymentGateway\Models\Gateway;
use Kreetancraft\PaymentGateway\Models\Payment;
use Stripe\Checkout\Session;
use Stripe\StripeClient;

uses(RefreshDatabase::class);

/**
 * Stripe, through its own hosted page.
 *
 * The previous flow created a PaymentIntent and returned no redirect URL, and
 * both ChargePaymentAction and the checkout screen read "success with no
 * redirect" as "paid" — so a buyer who had entered no card at all was marked
 * succeeded and shown a success page. These tests pin the behaviour that
 * replaced it.
 */
const WEBHOOK_SECRET = 'whsec_test_secret_for_signing';

function stripeGateway(): Gateway
{
    return Gateway::updateOrCreate(['code' => 'stripe'], [
        'label' => 'Stripe',
        'enabled' => true,
        'class' => StripeGateway::class,
        'currencies' => ['USD'],
        'credentials' => [
            'secret_key' => 'sk_test_x',
            'publishable_key' => 'pk_test_x',
            'webhook_secret' => WEBHOOK_SECRET,
        ],
    ]);
}

/**
 * A StripeClient whose `checkout->sessions` is ours.
 *
 * @param  array<string, mixed>  $session
 */
function stripeClientReturning(array $session, ?Closure $captureParams = null): StripeClient
{
    $sessions = Mockery::mock();
    $sessions->shouldReceive('create')->andReturnUsing(
        function (array $params, array $opts = []) use ($session, $captureParams): Session {
            if ($captureParams !== null) {
                $captureParams($params, $opts);
            }

            return Session::constructFrom($session);
        }
    );
    $sessions->shouldReceive('retrieve')->andReturn(Session::constructFrom($session));

    $client = Mockery::mock(StripeClient::class);
    $client->checkout = new class($sessions)
    {
        public function __construct(public mixed $sessions) {}
    };

    return $client;
}

/**
 * A real Stripe signature, so the verification path is genuinely exercised
 * rather than stubbed past.
 */
function signedStripeRequest(array $event, string $secret = WEBHOOK_SECRET): Request
{
    $payload = json_encode($event, JSON_THROW_ON_ERROR);
    $timestamp = time();
    $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

    $request = Request::create('/webhook/stripe', 'POST', [], [], [], [], $payload);
    $request->headers->set('Stripe-Signature', "t={$timestamp},v1={$signature}");

    return $request;
}

it('sends the buyer to a Stripe-hosted page', function (): void {
    $client = stripeClientReturning([
        'id' => 'cs_test_123',
        'url' => 'https://checkout.stripe.com/c/pay/cs_test_123',
    ]);

    $result = (new StripeGateway(stripeGateway(), $client))->charge([
        'amount_cents' => 2500,
        'currency' => 'USD',
        'description' => 'Trek deposit',
        'order_reference' => 'ORD-1',
    ]);

    expect($result->success)->toBeTrue()
        ->and($result->redirectUrl)->toBe('https://checkout.stripe.com/c/pay/cs_test_123')
        ->and($result->orderReference)->toBe('cs_test_123');
});

it('never pins the payment methods', function (): void {
    // Passing payment_method_types turns off dynamic payment methods, so the
    // buyer stops being offered whatever Stripe judges best for their currency
    // and country — and adding a method later becomes a code change.
    $seen = [];
    $client = stripeClientReturning(
        ['id' => 'cs_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_1'],
        function (array $params) use (&$seen): void {
            $seen = $params;
        }
    );

    (new StripeGateway(stripeGateway(), $client))->charge([
        'amount_cents' => 1000, 'currency' => 'USD', 'order_reference' => 'ORD-2',
    ]);

    expect($seen)->not->toHaveKey('payment_method_types')
        ->and($seen['mode'])->toBe('payment')
        ->and($seen['line_items'][0]['price_data']['unit_amount'])->toBe(1000)
        ->and($seen['line_items'][0]['price_data']['currency'])->toBe('usd');
});

it('asks Stripe to name the session on the way back', function (): void {
    $seen = [];
    $client = stripeClientReturning(
        ['id' => 'cs_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_1'],
        function (array $params) use (&$seen): void {
            $seen = $params;
        }
    );

    (new StripeGateway(stripeGateway(), $client))->charge([
        'amount_cents' => 1000, 'currency' => 'USD', 'order_reference' => 'ORD-3',
    ]);

    // The placeholder must survive un-encoded or Stripe cannot substitute it.
    expect($seen['success_url'])->toContain('session_id={CHECKOUT_SESSION_ID}')
        ->and($seen['cancel_url'])->not->toContain('{CHECKOUT_SESSION_ID}');
});

it('sends an idempotency key so a retry cannot charge twice', function (): void {
    $opts = [];
    $client = stripeClientReturning(
        ['id' => 'cs_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_1'],
        function (array $params, array $options) use (&$opts): void {
            $opts = $options;
        }
    );

    (new StripeGateway(stripeGateway(), $client))->charge([
        'amount_cents' => 1000, 'currency' => 'USD', 'reference_seed' => 'INV-7',
    ]);

    expect($opts)->toHaveKey('idempotency_key')
        ->and($opts['idempotency_key'])->toBe('charge:INV-7');
});

it('leaves the payment pending until Stripe says it was paid', function (): void {
    // The heart of it. Creating a session is not being paid.
    $client = stripeClientReturning([
        'id' => 'cs_test_pending',
        'url' => 'https://checkout.stripe.com/c/pay/cs_test_pending',
    ]);

    $result = (new StripeGateway(stripeGateway(), $client))->charge([
        'amount_cents' => 1000, 'currency' => 'USD', 'order_reference' => 'ORD-4',
    ]);

    expect($result->settled)->toBeFalse();
});

it('does not fulfil a completed session that is still unpaid', function (): void {
    // Delayed-notification methods complete the session while the money is
    // still in flight. Treating the event name as proof of payment grants
    // access for payments that later fail.
    $result = (new StripeGateway(stripeGateway()))->webhook(signedStripeRequest([
        'id' => 'evt_1',
        'type' => 'checkout.session.completed',
        'data' => ['object' => [
            'id' => 'cs_test_unpaid',
            'object' => 'checkout.session',
            'payment_status' => 'unpaid',
            'status' => 'complete',
            'amount_total' => 1000,
            'currency' => 'usd',
        ]],
    ]));

    expect($result->success)->toBeTrue()
        ->and($result->status)->toBe('pending');
});

it('fulfils a completed session that is paid', function (): void {
    $result = (new StripeGateway(stripeGateway()))->webhook(signedStripeRequest([
        'id' => 'evt_2',
        'type' => 'checkout.session.completed',
        'data' => ['object' => [
            'id' => 'cs_test_paid',
            'object' => 'checkout.session',
            'payment_status' => 'paid',
            'status' => 'complete',
            'amount_total' => 2500,
            'currency' => 'usd',
        ]],
    ]));

    expect($result->status)->toBe('succeeded')
        ->and($result->transactionId)->toBe('cs_test_paid')
        ->and($result->amount)->toBe(25.0);
});

it('fulfils the late success of a delayed payment', function (): void {
    $result = (new StripeGateway(stripeGateway()))->webhook(signedStripeRequest([
        'id' => 'evt_3',
        'type' => 'checkout.session.async_payment_succeeded',
        'data' => ['object' => [
            'id' => 'cs_test_async', 'object' => 'checkout.session',
            'payment_status' => 'paid', 'amount_total' => 500, 'currency' => 'usd',
        ]],
    ]));

    expect($result->status)->toBe('succeeded');
});

it('records the late failure of a delayed payment', function (): void {
    $result = (new StripeGateway(stripeGateway()))->webhook(signedStripeRequest([
        'id' => 'evt_4',
        'type' => 'checkout.session.async_payment_failed',
        'data' => ['object' => [
            'id' => 'cs_test_failed', 'object' => 'checkout.session',
            'payment_status' => 'unpaid', 'amount_total' => 500, 'currency' => 'usd',
        ]],
    ]));

    expect($result->status)->toBe('failed');
});

it('treats an expired session as cancelled', function (): void {
    $result = (new StripeGateway(stripeGateway()))->webhook(signedStripeRequest([
        'id' => 'evt_5',
        'type' => 'checkout.session.expired',
        'data' => ['object' => [
            'id' => 'cs_test_exp', 'object' => 'checkout.session',
            'payment_status' => 'unpaid', 'status' => 'expired',
            'amount_total' => 500, 'currency' => 'usd',
        ]],
    ]));

    expect($result->status)->toBe('canceled');
});

it('refuses a webhook signed with the wrong secret', function (): void {
    // Without this anyone who can reach the endpoint can mark any payment paid.
    $result = (new StripeGateway(stripeGateway()))->webhook(signedStripeRequest(
        [
            'id' => 'evt_6',
            'type' => 'checkout.session.completed',
            'data' => ['object' => ['id' => 'cs_x', 'payment_status' => 'paid']],
        ],
        'whsec_not_the_configured_secret'
    ));

    expect($result->success)->toBeFalse()
        ->and($result->errorMessage)->toContain('Signature verification failed');
});

it('refuses a webhook when no secret is configured', function (): void {
    $gateway = stripeGateway();
    $gateway->credentials = ['secret_key' => 'sk_test_x'];
    $gateway->save();

    $result = (new StripeGateway($gateway))->webhook(signedStripeRequest([
        'id' => 'evt_7', 'type' => 'checkout.session.completed',
        'data' => ['object' => ['id' => 'cs_y', 'payment_status' => 'paid']],
    ]));

    expect($result->success)->toBeFalse()
        ->and($result->errorMessage)->toBe('Webhook secret not configured');
});

it('reads payment_status when verifying a session', function (): void {
    $client = stripeClientReturning([
        'id' => 'cs_test_v', 'object' => 'checkout.session',
        'payment_status' => 'paid', 'status' => 'complete',
        'amount_total' => 4200, 'currency' => 'usd',
    ]);

    $result = (new StripeGateway(stripeGateway(), $client))->verify(['transaction_id' => 'cs_test_v']);

    expect($result->status)->toBe('succeeded')
        ->and($result->amount)->toBe(42.0)
        ->and($result->currency)->toBe('USD')
        ->and($result->paidAt)->not->toBeNull();
});

it('does not call the amount paid when the session is still open', function (): void {
    $client = stripeClientReturning([
        'id' => 'cs_test_open', 'object' => 'checkout.session',
        'payment_status' => 'unpaid', 'status' => 'open',
        'amount_total' => 4200, 'currency' => 'usd',
    ]);

    $result = (new StripeGateway(stripeGateway(), $client))->verify(['transaction_id' => 'cs_test_open']);

    expect($result->status)->toBe('pending')
        ->and($result->paidAt)->toBeNull();
});

it('marks the payment paid when the webhook lands', function (): void {
    // End to end through the action that owns the payments table, since that is
    // where a mismatch between the gateway's vocabulary and ours would show.
    $payment = Payment::factory()->create([
        'gateway' => 'stripe',
        'gateway_reference' => 'cs_test_land',
        'status' => PaymentStatus::Pending,
        'amount_cents' => 2500,
    ]);

    stripeGateway();

    $result = HandleWebhookAction::run(
        gateway: 'stripe',
        request: signedStripeRequest([
            'id' => 'evt_8',
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id' => 'cs_test_land', 'object' => 'checkout.session',
                'payment_status' => 'paid', 'status' => 'complete',
                'amount_total' => 2500, 'currency' => 'usd',
            ]],
        ])
    );

    expect($result->success)->toBeTrue()
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Succeeded)
        ->and($payment->fresh()->paid_at)->not->toBeNull();
});

it('does not mark the payment paid when the session is unpaid', function (): void {
    $payment = Payment::factory()->create([
        'gateway' => 'stripe',
        'gateway_reference' => 'cs_test_unpaid_land',
        'status' => PaymentStatus::Pending,
    ]);

    stripeGateway();

    HandleWebhookAction::run(
        gateway: 'stripe',
        request: signedStripeRequest([
            'id' => 'evt_9',
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id' => 'cs_test_unpaid_land', 'object' => 'checkout.session',
                'payment_status' => 'unpaid', 'status' => 'complete',
                'amount_total' => 2500, 'currency' => 'usd',
            ]],
        ])
    );

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($payment->fresh()->paid_at)->toBeNull();
});
