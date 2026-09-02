<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Kreetancraft\PaymentGateway\Actions\ChargePaymentAction;
use Kreetancraft\PaymentGateway\Contracts\GatewayResolver;
use Kreetancraft\PaymentGateway\Contracts\PaymentGateway;
use Kreetancraft\PaymentGateway\Data\PaymentResult;
use Kreetancraft\PaymentGateway\Enums\PaymentStatus;
use Kreetancraft\PaymentGateway\Models\Payment;
use Kreetancraft\PaymentGateway\Tests\Fixtures\Models\TestInvoice;

uses(RefreshDatabase::class);

/**
 * Where the amount comes from.
 *
 * `POST /checkout` is public, and ChargePaymentAction used to validate
 * `amount_cents` as `required|integer|min:1` — so the buyer chose the price.
 * Payment had no link to what was being bought, so there was nothing to check
 * an amount against even in principle. The monolith reads it off the invoice
 * server-side and lets the request choose only *which* server-computed amount;
 * this is the same idea behind a contract.
 */
function fakeGatewayAccepting(?callable $inspect = null): void
{
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('supportsCurrency')->andReturn(true);
    $gateway->shouldReceive('charge')->andReturnUsing(function (array $data) use ($inspect): PaymentResult {
        if ($inspect !== null) {
            $inspect($data);
        }

        return PaymentResult::success(orderReference: 'ref_1', redirectUrl: null, checkoutData: null);
    });

    $resolver = Mockery::mock(GatewayResolver::class);
    $resolver->shouldReceive('getDefaultDriver')->andReturn('stripe');
    $resolver->shouldReceive('resolve')->andReturn($gateway);
    app()->instance(GatewayResolver::class, $resolver);
}

it('ignores an amount supplied by the caller', function (): void {
    $seen = null;
    fakeGatewayAccepting(function (array $data) use (&$seen): void {
        $seen = $data['amount_cents'];
    });

    $invoice = TestInvoice::create(['number' => 'INV-1', 'currency' => 'USD', 'total_cents' => 50000]);

    ChargePaymentAction::run([
        'payable_type' => 'invoice',
        'payable_id' => $invoice->id,
        'amount_cents' => 1,          // the whole point
        'currency' => 'XXX',
    ]);

    expect($seen)->toBe(50000)
        ->and(Payment::first()->amount_cents)->toBe(50000)
        ->and(Payment::first()->currency)->toBe('USD');
});

it('charges only what is still outstanding', function (): void {
    fakeGatewayAccepting();

    $invoice = TestInvoice::create([
        'number' => 'INV-2', 'currency' => 'USD', 'total_cents' => 50000, 'paid_cents' => 30000,
    ]);

    ChargePaymentAction::run(['payable_type' => 'invoice', 'payable_id' => $invoice->id]);

    expect(Payment::first()->amount_cents)->toBe(20000);
});

it('refuses a payable that is already settled', function (): void {
    fakeGatewayAccepting();

    $invoice = TestInvoice::create([
        'number' => 'INV-3', 'currency' => 'USD', 'total_cents' => 50000, 'paid_cents' => 50000,
    ]);

    $result = ChargePaymentAction::run(['payable_type' => 'invoice', 'payable_id' => $invoice->id]);

    expect($result->success)->toBeFalse()
        ->and($result->errorCode)->toBe('nothing_to_pay')
        ->and(Payment::count())->toBe(0);
});

it('refuses a payable type that is not on the allowlist', function (): void {
    // Without the allowlist a caller could point checkout at any model in the
    // application and have it charged.
    fakeGatewayAccepting();

    $result = ChargePaymentAction::run([
        'payable_type' => 'user',
        'payable_id' => 1,
    ]);

    expect($result->success)->toBeFalse()
        ->and($result->errorCode)->toBe('payable_not_found');
});

it('refuses a payable that does not exist', function (): void {
    fakeGatewayAccepting();

    $result = ChargePaymentAction::run(['payable_type' => 'invoice', 'payable_id' => 9999]);

    expect($result->errorCode)->toBe('payable_not_found');
});

it('records what the payment is for', function (): void {
    fakeGatewayAccepting();

    $invoice = TestInvoice::create(['number' => 'INV-4', 'currency' => 'USD', 'total_cents' => 1000]);

    ChargePaymentAction::run(['payable_type' => 'invoice', 'payable_id' => $invoice->id]);

    $payment = Payment::first();

    expect($payment->payable_id)->toBe($invoice->id)
        ->and($payment->payable)->toBeInstanceOf(TestInvoice::class);
});

it('writes the payment row before calling the gateway', function (): void {
    // It used to charge first and create the row after, so a crash in between
    // took the buyer's money with nothing recorded locally.
    $existedDuringCharge = false;

    fakeGatewayAccepting(function () use (&$existedDuringCharge): void {
        $existedDuringCharge = Payment::query()->exists();
    });

    $invoice = TestInvoice::create(['number' => 'INV-5', 'currency' => 'USD', 'total_cents' => 1000]);

    ChargePaymentAction::run(['payable_type' => 'invoice', 'payable_id' => $invoice->id]);

    expect($existedDuringCharge)->toBeTrue();
});

it('does not create a second payment for the same payable', function (): void {
    fakeGatewayAccepting();

    $invoice = TestInvoice::create(['number' => 'INV-6', 'currency' => 'USD', 'total_cents' => 1000]);

    ChargePaymentAction::run(['payable_type' => 'invoice', 'payable_id' => $invoice->id]);
    ChargePaymentAction::run(['payable_type' => 'invoice', 'payable_id' => $invoice->id]);

    expect(Payment::count())->toBe(1);
});

it('keys idempotency on the payable, not on the request body', function (): void {
    // The old key was a hash of the whole payload, so two buyers paying the same
    // amount for the same thing with identical payloads collided — and adding
    // any field defeated it entirely.
    fakeGatewayAccepting();

    $invoice = TestInvoice::create(['number' => 'INV-7', 'currency' => 'USD', 'total_cents' => 1000]);

    ChargePaymentAction::run(['payable_type' => 'invoice', 'payable_id' => $invoice->id]);
    ChargePaymentAction::run([
        'payable_type' => 'invoice',
        'payable_id' => $invoice->id,
        'customer_name' => 'a field that was not there before',
    ]);

    expect(Payment::count())->toBe(1);

    // A different invoice is a different payment, even at the same price.
    $other = TestInvoice::create(['number' => 'INV-8', 'currency' => 'USD', 'total_cents' => 1000]);
    ChargePaymentAction::run(['payable_type' => 'invoice', 'payable_id' => $other->id]);

    expect(Payment::count())->toBe(2);
});

it('marks a failed charge failed without losing the record', function (): void {
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('supportsCurrency')->andReturn(true);
    $gateway->shouldReceive('charge')->andReturn(
        PaymentResult::failure(orderReference: '', errorMessage: 'declined', errorCode: 'card_declined')
    );

    $resolver = Mockery::mock(GatewayResolver::class);
    $resolver->shouldReceive('getDefaultDriver')->andReturn('stripe');
    $resolver->shouldReceive('resolve')->andReturn($gateway);
    app()->instance(GatewayResolver::class, $resolver);

    $invoice = TestInvoice::create(['number' => 'INV-9', 'currency' => 'USD', 'total_cents' => 1000]);

    ChargePaymentAction::run(['payable_type' => 'invoice', 'payable_id' => $invoice->id]);

    expect(Payment::first()->status)->toBe(PaymentStatus::Failed)
        ->and(Payment::first()->payable_id)->toBe($invoice->id);
});
