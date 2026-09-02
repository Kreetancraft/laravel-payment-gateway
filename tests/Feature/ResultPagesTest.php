<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Kreetancraft\PaymentGateway\Contracts\GatewayResolver;
use Kreetancraft\PaymentGateway\Contracts\PaymentGateway;
use Kreetancraft\PaymentGateway\Data\VerificationResult;
use Kreetancraft\PaymentGateway\Enums\PaymentStatus;
use Kreetancraft\PaymentGateway\Models\Payment;

uses(RefreshDatabase::class);

function verifyingGateway(string $status, bool $success = true): void
{
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('verify')->andReturn(
        $success
            ? VerificationResult::success(transactionId: 'cs_x', status: $status, amount: 5.0, currency: 'USD')
            : VerificationResult::failure(transactionId: 'cs_x', errorMessage: 'Card declined')
    );

    $resolver = Mockery::mock(GatewayResolver::class);
    $resolver->shouldReceive('resolve')->andReturn($gateway);
    $resolver->shouldReceive('getDefaultDriver')->andReturn('stripe');
    app()->instance(GatewayResolver::class, $resolver);
}

it('shows the amount and reference from the payment record', function (): void {
    verifyingGateway('succeeded');

    $payment = Payment::factory()->create([
        'gateway' => 'stripe',
        'gateway_reference' => 'cs_page_1',
        'status' => PaymentStatus::Succeeded,
        'amount_cents' => 42500,
        'currency' => 'USD',
        'paid_at' => now(),
    ]);

    $this->get('/payment/success?session_id=cs_page_1')
        ->assertOk()
        ->assertSee('Payment successful')
        ->assertSee($payment->reference)
        ->assertSee('425.00');
});

it('does not claim success while the payment is still confirming', function (): void {
    // Stripe answers "not settled yet" for a delayed payment method, and that
    // arrives as a *successful* verification with a pending status. The page
    // used to print "Payment Successful!" over it.
    verifyingGateway('pending');

    Payment::factory()->create([
        'gateway' => 'stripe',
        'gateway_reference' => 'cs_page_2',
        'status' => PaymentStatus::Pending,
        'amount_cents' => 500,
        'currency' => 'USD',
    ]);

    $this->get('/payment/success?session_id=cs_page_2')
        ->assertOk()
        ->assertSee('Payment received')
        ->assertSee('Confirming')
        ->assertDontSee('Payment successful');
});

it('survives the exact url a real Stripe return produced', function (): void {
    verifyingGateway('succeeded');

    $payment = Payment::factory()->create([
        'gateway' => 'stripe',
        'gateway_reference' => 'cs_test_a1vvHJ2swnz19HKu1L1CPwU9H2vu38d59APKYzxU7g96JQzeQzKNXdppea',
        'status' => PaymentStatus::Succeeded,
        'paid_at' => now(),
    ]);

    $this->get('/payment/success?reference=&session_id='.$payment->gateway_reference)
        ->assertOk()
        ->assertSee($payment->reference);
});

it('renders the failed page without a payment record', function (): void {
    // A buyer can land here with nothing we recognise; it must not 500.
    $this->get('/payment/failed?reference=nothing-we-know')
        ->assertOk()
        ->assertSee('Payment failed');
});

it('will not let a query string fail a settled payment', function (): void {
    // This route updated whatever matched, unauthenticated. Anyone could mark
    // somebody else's paid payment as failed by guessing a reference.
    $payment = Payment::factory()->create([
        'status' => PaymentStatus::Succeeded,
        'paid_at' => now(),
    ]);

    $this->get('/payment/failed?reference='.$payment->reference)->assertOk();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Succeeded);
});

it('will not let a query string cancel a settled payment', function (): void {
    $payment = Payment::factory()->create([
        'status' => PaymentStatus::Succeeded,
        'paid_at' => now(),
    ]);

    $this->get('/payment/cancel?reference='.$payment->reference)->assertOk();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Succeeded);
});

it('still cancels an attempt that was genuinely open', function (): void {
    $payment = Payment::factory()->create(['status' => PaymentStatus::Pending]);

    $this->get('/payment/cancel?reference='.$payment->reference)->assertOk();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Canceled);
});
