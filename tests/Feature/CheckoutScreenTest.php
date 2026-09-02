<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Kreetancraft\PaymentGateway\Contracts\GatewayResolver;
use Kreetancraft\PaymentGateway\Contracts\PaymentGateway;
use Kreetancraft\PaymentGateway\Data\PaymentResult;
use Kreetancraft\PaymentGateway\Livewire\Checkout;
use Kreetancraft\PaymentGateway\Models\Coupon;
use Kreetancraft\PaymentGateway\Models\Payment;
use Kreetancraft\PaymentGateway\Tests\Fixtures\Models\TestInvoice;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * The checkout screen, which nothing had ever exercised.
 *
 * That gap is why the original amount hole survived — the screen took a price
 * from the query string and from a field the buyer could type into — and why
 * moving the API path onto payables silently broke this one without turning the
 * suite red. Both are covered now.
 */
function acceptingGateway(?callable $inspect = null): void
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
    $resolver->shouldReceive('getEnabledGateways')->andReturn(['stripe']);
    $resolver->shouldReceive('getGatewayConfig')->andReturn(null);
    app()->instance(GatewayResolver::class, $resolver);
}

it('shows what the payable owes', function (): void {
    $invoice = TestInvoice::create(['number' => 'INV-1', 'currency' => 'USD', 'total_cents' => 42500]);

    Livewire::test(Checkout::class, ['payableType' => 'invoice', 'payableId' => $invoice->id])
        ->assertOk()
        ->assertSet('payableId', $invoice->id);

    expect(Livewire::test(Checkout::class, ['payableType' => 'invoice', 'payableId' => $invoice->id])
        ->instance()->getAmountInCents())->toBe(42500);
});

it('takes the currency from the payable, not the request', function (): void {
    $invoice = TestInvoice::create(['number' => 'INV-2', 'currency' => 'NPR', 'total_cents' => 1000]);

    $component = Livewire::test(Checkout::class, [
        'payableType' => 'invoice',
        'payableId' => $invoice->id,
        'currency' => 'USD',   // ignored
    ]);

    expect($component->instance()->getCurrencyCode())->toBe('NPR');
});

it('ignores an amount in the link', function (): void {
    // The old screen would have believed this.
    $invoice = TestInvoice::create(['number' => 'INV-3', 'currency' => 'USD', 'total_cents' => 50000]);

    $component = Livewire::test(Checkout::class, [
        'payableType' => 'invoice',
        'payableId' => $invoice->id,
        'amount' => 1,
    ]);

    expect($component->instance()->getAmountInCents())->toBe(50000);
});

it('charges what the payable owes', function (): void {
    $seen = null;
    acceptingGateway(function (array $data) use (&$seen): void {
        $seen = $data['amount_cents'];
    });

    $invoice = TestInvoice::create(['number' => 'INV-4', 'currency' => 'USD', 'total_cents' => 25000]);

    Livewire::test(Checkout::class, ['payableType' => 'invoice', 'payableId' => $invoice->id])
        ->set('selectedGateway', 'stripe')
        ->set('customerEmail', 'buyer@example.com')
        ->call('charge');

    expect($seen)->toBe(25000)
        ->and(Payment::first()->amount_cents)->toBe(25000);
});

it('applies a coupon against the payable total', function (): void {
    $seen = null;
    acceptingGateway(function (array $data) use (&$seen): void {
        $seen = $data['amount_cents'];
    });

    Coupon::create([
        'code' => 'TENOFF',
        'name' => 'Ten off',
        'type' => 'fixed',
        'value' => 100,          // 100 currency units
        'is_active' => true,
    ]);

    $invoice = TestInvoice::create(['number' => 'INV-5', 'currency' => 'USD', 'total_cents' => 50000]);

    Livewire::test(Checkout::class, ['payableType' => 'invoice', 'payableId' => $invoice->id])
        ->set('selectedGateway', 'stripe')
        ->set('customerEmail', 'buyer@example.com')
        ->set('couponCode', 'TENOFF')
        ->call('applyCoupon')
        ->call('charge');

    // Discounted, and less than the full amount — the exact figure depends on
    // the coupon type, but it must not be the undiscounted total.
    expect($seen)->toBeLessThan(50000)
        ->and($seen)->toBeGreaterThan(0);
});

it('refuses a coupon that does not exist', function (): void {
    acceptingGateway();

    $invoice = TestInvoice::create(['number' => 'INV-6', 'currency' => 'USD', 'total_cents' => 50000]);

    Livewire::test(Checkout::class, ['payableType' => 'invoice', 'payableId' => $invoice->id])
        ->set('couponCode', 'NOPE')
        ->call('applyCoupon')
        ->assertSet('appliedCouponCode', null);
});

it('will not charge without a payable', function (): void {
    acceptingGateway();

    Livewire::test(Checkout::class)
        ->set('selectedGateway', 'stripe')
        ->set('customerEmail', 'buyer@example.com')
        ->call('charge');

    expect(Payment::count())->toBe(0);
});

it('will not charge a payable that is already settled', function (): void {
    acceptingGateway();

    $invoice = TestInvoice::create([
        'number' => 'INV-7', 'currency' => 'USD', 'total_cents' => 50000, 'paid_cents' => 50000,
    ]);

    Livewire::test(Checkout::class, ['payableType' => 'invoice', 'payableId' => $invoice->id])
        ->set('selectedGateway', 'stripe')
        ->set('customerEmail', 'buyer@example.com')
        ->call('charge');

    expect(Payment::count())->toBe(0);
});

it('records the payable on the payment', function (): void {
    acceptingGateway();

    $invoice = TestInvoice::create(['number' => 'INV-8', 'currency' => 'USD', 'total_cents' => 1000]);

    Livewire::test(Checkout::class, ['payableType' => 'invoice', 'payableId' => $invoice->id])
        ->set('selectedGateway', 'stripe')
        ->set('customerEmail', 'buyer@example.com')
        ->call('charge');

    expect(Payment::first()->payable_id)->toBe($invoice->id);
});
