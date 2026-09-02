<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Kreetancraft\PaymentGateway\Actions\ChargePaymentAction;
use Kreetancraft\PaymentGateway\Actions\VerifyPaymentAction;
use Kreetancraft\PaymentGateway\Contracts\GatewayResolver;
use Kreetancraft\PaymentGateway\Contracts\PaymentGateway;
use Kreetancraft\PaymentGateway\Data\PaymentResult;
use Kreetancraft\PaymentGateway\Data\VerificationResult;
use Kreetancraft\PaymentGateway\Enums\PaymentStatus;
use Kreetancraft\PaymentGateway\Models\Payment;
use Kreetancraft\PaymentGateway\Tests\Fixtures\Models\TestInvoice;

uses(RefreshDatabase::class);

/**
 * Coming back from the hosted page.
 *
 * A real Stripe return looked like this:
 *
 *   /payment/success?reference=&session_id=cs_test_a1vvHJ2swn...
 *
 * `reference=` is empty, and three defects compounded from there. The buyer had
 * paid and was told the payment could not be verified.
 */
it('gives the gateway a reference to send the buyer back with', function (): void {
    // ChargePaymentAction sent `reference_seed`; StripeGateway read
    // `order_reference`. Nothing joined them, so the return URL carried
    // `reference=` and the success page had nothing to look up.
    $seen = null;

    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('supportsCurrency')->andReturn(true);
    $gateway->shouldReceive('charge')->andReturnUsing(function (array $data) use (&$seen): PaymentResult {
        $seen = $data['order_reference'] ?? null;

        return PaymentResult::success(orderReference: 'cs_test_1', redirectUrl: 'https://pay.example');
    });

    $resolver = Mockery::mock(GatewayResolver::class);
    $resolver->shouldReceive('getDefaultDriver')->andReturn('stripe');
    $resolver->shouldReceive('resolve')->andReturn($gateway);
    app()->instance(GatewayResolver::class, $resolver);

    $invoice = TestInvoice::create(['number' => 'INV-R1', 'currency' => 'USD', 'total_cents' => 500]);

    ChargePaymentAction::run(['payable_type' => 'invoice', 'payable_id' => $invoice->id]);

    expect($seen)->not->toBeNull()
        ->and($seen)->not->toBe('')
        ->and($seen)->toBe(Payment::first()->reference);
});

it('finds the payment from the session id alone', function (): void {
    // `session_id` was not among the lookup keys at all.
    $payment = Payment::factory()->create([
        'gateway' => 'stripe',
        'gateway_reference' => 'cs_test_lookup',
        'status' => PaymentStatus::Pending,
    ]);

    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('verify')->andReturn(
        VerificationResult::success(transactionId: 'cs_test_lookup', status: 'succeeded', amount: 5.0, currency: 'USD')
    );

    $resolver = Mockery::mock(GatewayResolver::class);
    $resolver->shouldReceive('resolve')->with('stripe')->andReturn($gateway);
    $resolver->shouldReceive('getDefaultDriver')->andReturn('himalayan');
    app()->instance(GatewayResolver::class, $resolver);

    $result = VerifyPaymentAction::run(['session_id' => 'cs_test_lookup']);

    expect($result->success)->toBeTrue()
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Succeeded);
});

it('looks past a blank reference to the session id', function (): void {
    // The exact shape of the reported URL. `??` only falls through on null, and
    // a query string gives an empty string — so the chain stopped dead here.
    $payment = Payment::factory()->create([
        'gateway' => 'stripe',
        'gateway_reference' => 'cs_test_blankref',
        'status' => PaymentStatus::Pending,
    ]);

    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('verify')->andReturn(
        VerificationResult::success(transactionId: 'cs_test_blankref', status: 'succeeded', amount: 5.0, currency: 'USD')
    );

    $resolver = Mockery::mock(GatewayResolver::class);
    $resolver->shouldReceive('resolve')->with('stripe')->andReturn($gateway);
    $resolver->shouldReceive('getDefaultDriver')->andReturn('himalayan');
    app()->instance(GatewayResolver::class, $resolver);

    $result = VerifyPaymentAction::run(['reference' => '', 'session_id' => 'cs_test_blankref']);

    expect($result->success)->toBeTrue()
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Succeeded);
});

it('asks the gateway that took the payment, not the default one', function (): void {
    // The worst of the three. With no payment found, the code fell back to the
    // default driver, so a Stripe session id was handed to the bank's verifier.
    $payment = Payment::factory()->create([
        'gateway' => 'stripe',
        'gateway_reference' => 'cs_test_right_gw',
        'status' => PaymentStatus::Pending,
    ]);

    $stripe = Mockery::mock(PaymentGateway::class);
    $stripe->shouldReceive('verify')->once()->andReturn(
        VerificationResult::success(transactionId: 'cs_test_right_gw', status: 'succeeded', amount: 5.0, currency: 'USD')
    );

    $himalayan = Mockery::mock(PaymentGateway::class);
    $himalayan->shouldReceive('verify')->never();

    $resolver = Mockery::mock(GatewayResolver::class);
    $resolver->shouldReceive('resolve')->with('stripe')->andReturn($stripe);
    $resolver->shouldReceive('resolve')->with('himalayan')->andReturn($himalayan);
    $resolver->shouldReceive('getDefaultDriver')->andReturn('himalayan');
    app()->instance(GatewayResolver::class, $resolver);

    VerifyPaymentAction::run(['session_id' => 'cs_test_right_gw']);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Succeeded);
});

it('refuses to guess a gateway for a reference it cannot place', function (): void {
    // Better a clear "we cannot find that payment" than asking the wrong
    // provider about someone else's reference.
    $wrong = Mockery::mock(PaymentGateway::class);
    $wrong->shouldReceive('verify')->never();

    $resolver = Mockery::mock(GatewayResolver::class);
    $resolver->shouldReceive('resolve')->andReturn($wrong);
    $resolver->shouldReceive('getDefaultDriver')->andReturn('himalayan');
    app()->instance(GatewayResolver::class, $resolver);

    $result = VerifyPaymentAction::run(['session_id' => 'cs_test_unknown_to_us']);

    expect($result->success)->toBeFalse()
        ->and($result->errorMessage)->toContain('No payment matches');
});
