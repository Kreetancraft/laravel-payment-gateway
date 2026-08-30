<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Kreetancraft\PaymentGateway\Gateways\StripeGateway;
use Kreetancraft\PaymentGateway\Livewire\CreateCoupon;
use Kreetancraft\PaymentGateway\Livewire\EditCoupon;
use Kreetancraft\PaymentGateway\Livewire\EditGateway;
use Kreetancraft\PaymentGateway\Livewire\ManageCoupons;
use Kreetancraft\PaymentGateway\Livewire\ManageGateways;
use Kreetancraft\PaymentGateway\Livewire\ManageTransactions;
use Kreetancraft\PaymentGateway\Livewire\ShowCoupon;
use Kreetancraft\PaymentGateway\Models\Coupon;
use Kreetancraft\PaymentGateway\Models\Gateway;
use Kreetancraft\PaymentGateway\Models\Payment;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('renders manage gateways screen and toggles status', function (): void {
    $gateway = Gateway::create([
        'code' => 'stripe',
        'label' => 'Stripe',
        'enabled' => true,
        'class' => StripeGateway::class,
        'currencies' => ['USD', 'EUR'],
        'capabilities' => ['charge', 'refund'],
    ]);

    Livewire::test(ManageGateways::class)
        ->assertStatus(200)
        ->assertSee('Stripe')
        ->call('toggleGatewayEnabled', 'stripe');

    expect($gateway->fresh()->enabled)->toBeFalse();
});

it('configures gateway credentials on dedicated EditGateway page', function (): void {
    $gateway = Gateway::create([
        'code' => 'stripe',
        'label' => 'Stripe',
        'enabled' => true,
        'class' => StripeGateway::class,
        'config_fields' => [
            ['key' => 'secret_key', 'label' => 'Secret Key', 'type' => 'password'],
        ],
    ]);

    Livewire::test(EditGateway::class, ['code' => 'stripe'])
        ->assertStatus(200)
        ->set('label', 'Stripe Gateway Pro')
        ->set('fieldValues.secret_key', 'sk_live_new_encrypted_secret')
        ->call('save')
        ->assertHasNoErrors();

    $fresh = $gateway->fresh();
    expect($fresh->label)->toBe('Stripe Gateway Pro')
        ->and($fresh->getStripeSecretKey())->toBe('sk_live_new_encrypted_secret');
});

it('creates coupon on dedicated CreateCoupon page', function (): void {
    Livewire::test(CreateCoupon::class)
        ->assertStatus(200)
        ->call('applyTemplate', 'percent_20')
        ->set('code', 'SUMMER2026')
        ->set('name', 'Summer Sale 20%')
        ->call('save')
        ->assertHasNoErrors();

    $coupon = Coupon::where('code', 'SUMMER2026')->first();
    expect($coupon)->not->toBeNull()
        ->and($coupon->value)->toBe(20)
        ->and($coupon->type)->toBe('percentage');
});

it('edits coupon on dedicated EditCoupon page', function (): void {
    $coupon = Coupon::create([
        'code' => 'WINTER50',
        'name' => 'Winter Sale',
        'type' => 'fixed',
        'value' => 5000,
        'is_active' => true,
    ]);

    Livewire::test(EditCoupon::class, ['id' => $coupon->id])
        ->assertStatus(200)
        ->set('name', 'Winter Mega Sale')
        ->set('value', 6000)
        ->call('save')
        ->assertHasNoErrors();

    $fresh = $coupon->fresh();
    expect($fresh->name)->toBe('Winter Mega Sale')
        ->and($fresh->value)->toBe(6000);
});

it('shows coupon details and redemptions on ShowCoupon page', function (): void {
    $coupon = Coupon::create([
        'code' => 'SHOWME',
        'name' => 'Show Test',
        'type' => 'percentage',
        'value' => 15,
        'is_active' => true,
    ]);

    Livewire::test(ShowCoupon::class, ['id' => $coupon->id])
        ->assertStatus(200)
        ->assertSee('SHOWME')
        ->assertSee('15% OFF');
});

it('duplicates and deletes coupon on ManageCoupons page', function (): void {
    $coupon = Coupon::create([
        'code' => 'CLONE10',
        'name' => 'Clone Me',
        'type' => 'percentage',
        'value' => 10,
        'is_active' => true,
    ]);

    Livewire::test(ManageCoupons::class)
        ->assertStatus(200)
        ->call('duplicate', $coupon->id);

    $duplicated = Coupon::where('code', 'like', 'CLONE10-COPY-%')->first();
    expect($duplicated)->not->toBeNull();

    Livewire::test(ManageCoupons::class)
        ->call('delete', $coupon->id);

    expect(Coupon::find($coupon->id))->toBeNull();
});

it('renders manage transactions screen', function (): void {
    Payment::create([
        'reference' => 'PAY-TEST-12345',
        'gateway' => 'stripe',
        'amount_cents' => 9900,
        'currency' => 'usd',
        'status' => 'succeeded',
        'customer_email' => 'customer@example.com',
    ]);

    Livewire::test(ManageTransactions::class)
        ->assertStatus(200)
        ->assertSee('PAY-TEST-12345')
        ->assertSee('customer@example.com');
});
