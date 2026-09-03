<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Kreetancraft\PaymentGateway\Actions\ChargePaymentAction;
use Kreetancraft\PaymentGateway\Contracts\GatewayResolver;
use Kreetancraft\PaymentGateway\Contracts\PaymentGateway;
use Kreetancraft\PaymentGateway\Data\PaymentResult;
use Kreetancraft\PaymentGateway\Enums\PaymentStatus;
use Kreetancraft\PaymentGateway\Livewire\Checkout;
use Kreetancraft\PaymentGateway\Models\Payment;
use Kreetancraft\PaymentGateway\Tests\Fixtures\Models\TestInvoice;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Paying a deposit now and the balance later.
 *
 * The request picks which of two amounts, never what either is worth — the same
 * rule that stopped a buyer choosing their own price. A booking secured with 20%
 * is the reason the distinction exists at all.
 */
function chargeCapturing(?callable $inspect = null): void
{
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('supportsCurrency')->andReturn(true);
    $gateway->shouldReceive('charge')->andReturnUsing(function (array $data) use ($inspect): PaymentResult {
        if ($inspect !== null) {
            $inspect($data);
        }

        return PaymentResult::success(orderReference: 'ref_'.uniqid(), redirectUrl: 'https://pay.example');
    });

    $resolver = Mockery::mock(GatewayResolver::class);
    $resolver->shouldReceive('getDefaultDriver')->andReturn('stripe');
    $resolver->shouldReceive('resolve')->andReturn($gateway);
    $resolver->shouldReceive('getEnabledGateways')->andReturn(['stripe']);
    $resolver->shouldReceive('getGatewayConfig')->andReturn(null);
    app()->instance(GatewayResolver::class, $resolver);
}

function bookingInvoice(int $total = 100000, int $deposit = 20000): TestInvoice
{
    return TestInvoice::create([
        'number' => 'INV-'.uniqid(),
        'currency' => 'USD',
        'total_cents' => $total,
        'deposit_cents' => $deposit,
    ]);
}

it('charges the deposit, not the whole invoice', function (): void {
    $seen = null;
    chargeCapturing(function (array $data) use (&$seen): void {
        $seen = $data['amount_cents'];
    });

    $invoice = bookingInvoice(100000, 20000);

    ChargePaymentAction::run([
        'payable_type' => 'invoice',
        'payable_id' => $invoice->id,
        'amount_type' => 'deposit',
    ]);

    expect($seen)->toBe(20000);
});

it('charges the whole balance by default', function (): void {
    $seen = null;
    chargeCapturing(function (array $data) use (&$seen): void {
        $seen = $data['amount_cents'];
    });

    $invoice = bookingInvoice(100000, 20000);

    ChargePaymentAction::run(['payable_type' => 'invoice', 'payable_id' => $invoice->id]);

    expect($seen)->toBe(100000);
});

it('asks only for what is left of the deposit', function (): void {
    // Somebody who paid part of their deposit owes the rest, not all of it again.
    $seen = null;
    chargeCapturing(function (array $data) use (&$seen): void {
        $seen = $data['amount_cents'];
    });

    $invoice = bookingInvoice(100000, 20000);

    Payment::factory()->create([
        'payable_type' => $invoice->getMorphClass(),
        'payable_id' => $invoice->id,
        'currency' => 'USD',
        'amount_cents' => 5000,
        'status' => PaymentStatus::Succeeded,
    ]);

    ChargePaymentAction::run([
        'payable_type' => 'invoice',
        'payable_id' => $invoice->id,
        'amount_type' => 'deposit',
    ]);

    expect($seen)->toBe(15000);
});

it('refuses a deposit that has already been paid', function (): void {
    chargeCapturing();

    $invoice = bookingInvoice(100000, 20000);

    Payment::factory()->create([
        'payable_type' => $invoice->getMorphClass(),
        'payable_id' => $invoice->id,
        'currency' => 'USD',
        'amount_cents' => 20000,
        'status' => PaymentStatus::Succeeded,
    ]);

    $result = ChargePaymentAction::run([
        'payable_type' => 'invoice',
        'payable_id' => $invoice->id,
        'amount_type' => 'deposit',
    ]);

    expect($result->success)->toBeFalse()
        ->and($result->errorCode)->toBe('deposit_already_paid');
});

it('refuses a deposit on something that does not take one', function (): void {
    // Payable stays a single-payment contract; SupportsDeposit is opt-in, so a
    // host that never wanted the feature is unaffected.
    chargeCapturing();

    $invoice = bookingInvoice(100000, 0);

    $result = ChargePaymentAction::run([
        'payable_type' => 'invoice',
        'payable_id' => $invoice->id,
        'amount_type' => 'deposit',
    ]);

    expect($result->success)->toBeFalse()
        ->and($result->errorCode)->toBe('deposit_already_paid');
});

it('never asks for more than the invoice still owes', function (): void {
    // A deposit larger than the remaining balance would overcharge the last
    // instalment.
    $seen = null;
    chargeCapturing(function (array $data) use (&$seen): void {
        $seen = $data['amount_cents'];
    });

    $invoice = bookingInvoice(10000, 50000);

    ChargePaymentAction::run([
        'payable_type' => 'invoice',
        'payable_id' => $invoice->id,
        'amount_type' => 'deposit',
    ]);

    expect($seen)->toBe(10000);
});

it('does not let a deposit and a balance collide on one key', function (): void {
    // Two different amounts for one payable are two attempts, not a repeat.
    chargeCapturing();

    $invoice = bookingInvoice(100000, 20000);

    ChargePaymentAction::run(['payable_type' => 'invoice', 'payable_id' => $invoice->id, 'amount_type' => 'deposit']);
    ChargePaymentAction::run(['payable_type' => 'invoice', 'payable_id' => $invoice->id, 'amount_type' => 'full']);

    expect(Payment::count())->toBe(2);
});

it('shows the deposit on the checkout screen', function (): void {
    // The screen must display the figure that will actually be charged.
    chargeCapturing();

    $invoice = bookingInvoice(100000, 20000);

    $component = Livewire::test(Checkout::class, [
        'payableType' => 'invoice',
        'payableId' => $invoice->id,
    ])->set('amountType', 'deposit');

    expect($component->instance()->getAmountInCents())->toBe(20000);
});

it('shows nothing owed when a deposit was asked for and there is none', function (): void {
    // Honest rather than helpful. Quietly falling back to the full balance would
    // charge someone the whole invoice when they asked to pay a deposit; showing
    // zero, and refusing at checkout, tells them what is actually true.
    chargeCapturing();

    $invoice = bookingInvoice(100000, 0);

    $component = Livewire::test(Checkout::class, [
        'payableType' => 'invoice',
        'payableId' => $invoice->id,
    ])->set('amountType', 'deposit');

    expect($component->instance()->getAmountInCents())->toBe(0);
});
