<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Kreetancraft\PaymentGateway\Contracts\GatewayResolver;
use Kreetancraft\PaymentGateway\Contracts\PaymentGateway;
use Kreetancraft\PaymentGateway\Data\RefundResult;
use Kreetancraft\PaymentGateway\Enums\PaymentStatus;
use Kreetancraft\PaymentGateway\Livewire\ManageTransactions;
use Kreetancraft\PaymentGateway\Models\Payment;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * The Refund button on the transactions screen.
 *
 * It called `$result->successful()`. `RefundResult` has no such method and
 * `Spatie\LaravelData\Data` has no `__call`, so every press threw
 * "Call to undefined method" — the only refund control in the package could
 * never have worked. The suite missed it entirely because the refund tests call
 * the action directly and nothing drove the component.
 */
function refundingGateway(bool $succeeds = true): void
{
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('refund')->andReturn(
        $succeeds
            ? RefundResult::success(transactionId: 'ref_1', amount: 25.0, refundId: 're_1')
            : RefundResult::failure(transactionId: 'ref_1', amount: 25.0, errorMessage: 'Already refunded')
    );

    $resolver = Mockery::mock(GatewayResolver::class);
    $resolver->shouldReceive('resolve')->andReturn($gateway);
    $resolver->shouldReceive('getDefaultDriver')->andReturn('stripe');
    app()->instance(GatewayResolver::class, $resolver);
}

it('does not fatal when the refund button is pressed', function (): void {
    refundingGateway();

    $payment = Payment::factory()->create([
        'gateway' => 'stripe',
        'gateway_reference' => 'ref_1',
        'status' => PaymentStatus::Succeeded,
        'amount_cents' => 2500,
        'currency' => 'USD',
        'paid_at' => now(),
    ]);

    Livewire::test(ManageTransactions::class)
        ->call('refund', $payment->id)
        ->assertOk();

    expect($payment->fresh()->refunded_amount_cents)->toBe(2500);
});

it('does not fatal when the refund is rejected either', function (): void {
    refundingGateway(succeeds: false);

    $payment = Payment::factory()->create([
        'gateway' => 'stripe',
        'gateway_reference' => 'ref_1',
        'status' => PaymentStatus::Succeeded,
        'amount_cents' => 2500,
        'currency' => 'USD',
        'paid_at' => now(),
    ]);

    Livewire::test(ManageTransactions::class)
        ->call('refund', $payment->id)
        ->assertOk();

    expect($payment->fresh()->refunded_amount_cents)->toBe(0);
});

it('puts a real amount in the exported csv', function (): void {
    // The column read `$p->amount`, which is not an attribute on Payment, so
    // every CSV ever exported had an empty Amount column.
    Payment::factory()->create([
        'gateway' => 'stripe',
        'status' => PaymentStatus::Succeeded,
        'amount_cents' => 4250,
        'currency' => 'USD',
    ]);

    $component = Livewire::test(ManageTransactions::class)->instance();

    ob_start();
    $component->exportCsv()->sendContent();
    $csv = (string) ob_get_clean();

    expect($csv)->toContain('42.50');
});
