<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Kreetancraft\PaymentGateway\Gateways\StripeGateway;
use Kreetancraft\PaymentGateway\Livewire\ManageCoupons;
use Kreetancraft\PaymentGateway\Livewire\ManageGateways;
use Kreetancraft\PaymentGateway\Models\Coupon;
use Kreetancraft\PaymentGateway\Models\Gateway;
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

it('opens edit modal and saves encrypted gateway credentials', function (): void {
    $gateway = Gateway::create([
        'code' => 'stripe',
        'label' => 'Stripe',
        'enabled' => true,
        'class' => StripeGateway::class,
        'config_fields' => [
            ['key' => 'secret_key', 'label' => 'Secret Key', 'type' => 'password'],
        ],
    ]);

    Livewire::test(ManageGateways::class)
        ->call('openEditGatewayModal', 'stripe')
        ->assertSet('editingCode', 'stripe')
        ->assertSet('showEditModal', true)
        ->set('editingLabel', 'Stripe Gateway Pro')
        ->set('fieldValues.secret_key', 'sk_live_new_encrypted_secret')
        ->call('saveGatewayCredentials')
        ->assertSet('showEditModal', false);

    $fresh = $gateway->fresh();
    expect($fresh->label)->toBe('Stripe Gateway Pro')
        ->and($fresh->getStripeSecretKey())->toBe('sk_live_new_encrypted_secret');
});

it('creates and edits coupons via Livewire ManageCoupons', function (): void {
    Livewire::test(ManageCoupons::class)
        ->call('create')
        ->assertSet('showCreateModal', true)
        ->set('code', 'SUMMER2026')
        ->set('name', 'Summer Sale 20%')
        ->set('type', 'percentage')
        ->set('value', 20)
        ->call('save')
        ->assertSet('showCreateModal', false);

    $coupon = Coupon::where('code', 'SUMMER2026')->first();
    expect($coupon)->not->toBeNull()
        ->and($coupon->value)->toBe(20);

    Livewire::test(ManageCoupons::class)
        ->call('edit', $coupon->id)
        ->assertSet('showEditModal', true)
        ->set('name', 'Summer Sale Updated')
        ->call('update')
        ->assertSet('showEditModal', false);

    expect($coupon->fresh()->name)->toBe('Summer Sale Updated');
});
