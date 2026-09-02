<?php

namespace Kreetancraft\PaymentGateway\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Kreetancraft\PaymentGateway\Contracts\GatewayResolver;
use Kreetancraft\PaymentGateway\Gateways\HimalayanBankGateway;
use Kreetancraft\PaymentGateway\Gateways\StripeGateway;
use Kreetancraft\PaymentGateway\Models\Gateway;
use Kreetancraft\PaymentGateway\PaymentGatewayManager;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Gateway::create([
        'code' => 'stripe',
        'label' => 'Pay with Stripe',
        'icon' => 'https://js.stripe.com/v3/stripe-logo.svg',
        'enabled' => true,
        'class' => StripeGateway::class,
        'currencies' => ['USD', 'EUR'],
        'capabilities' => ['charge', 'refund', 'webhook', 'verify'],
        'checkout_redirect' => false,
        'config_fields' => [
            ['key' => 'secret_key', 'required' => true],
        ],
        'credentials' => [
            'secret_key' => 'sk_test_mock',
            'publishable_key' => 'pk_test_mock',
        ],
    ]);

    Gateway::create([
        'code' => 'himalayan',
        'label' => 'Himalayan Bank (2C2P PACO)',
        'icon' => 'https://www.himalayanbank.com/themes/himalayan/assets/ico/hbl-icon.png',
        'enabled' => false,
        'class' => HimalayanBankGateway::class,
        'currencies' => ['NPR', 'USD'],
        'capabilities' => ['charge', 'refund', 'webhook', 'verify'],
        'checkout_redirect' => true,
        'config_fields' => [
            ['key' => 'office_id', 'required' => true],
            ['key' => 'api_key', 'required' => true],
        ],
        'credentials' => [
            'office_id' => '9104137120',
            'api_key' => 'test_api_key',
        ],
    ]);
});

it('filters enabled gateways only from database', function (): void {
    $resolver = app(GatewayResolver::class);
    $enabled = $resolver->getEnabledGateways();

    expect($enabled)->toContain('stripe')
        ->and($enabled)->not->toContain('himalayan');
});

it('returns default driver correctly', function (): void {
    $manager = app(PaymentGatewayManager::class);
    $driver = $manager->getDefaultDriver();

    expect($driver)->toBe('stripe');
});

it('resolves gateway class correctly from database', function (): void {
    $manager = app(PaymentGatewayManager::class);
    $driver = $manager->createDriver('stripe');

    expect($driver)->toBeInstanceOf(StripeGateway::class);
});

it('throws on unknown gateway driver', function (): void {
    $manager = app(PaymentGatewayManager::class);

    expect(fn () => $manager->createDriver('nonexistent'))
        ->toThrow(InvalidArgumentException::class);
});

it('checks currency support correctly', function (): void {
    $manager = app(PaymentGatewayManager::class);
    $driver = $manager->createDriver('stripe');

    expect($driver->supportsCurrency('USD'))->toBeTrue()
        ->and($driver->supportsCurrency('XYZ'))->toBeFalse();
});

it('returns correct gateway label and icon', function (): void {
    $manager = app(PaymentGatewayManager::class);
    $driver = $manager->createDriver('stripe');

    expect($driver->getLabel())->toBe('Pay with Stripe')
        ->and($driver->getIcon())->toBe('https://js.stripe.com/v3/stripe-logo.svg');
});

it('checks checkout redirect correctly', function (): void {
    $manager = app(PaymentGatewayManager::class);

    $stripe = $manager->createDriver('stripe');
    expect($stripe->checkoutRedirect())->toBeFalse();

    // Enable himalayan in DB to test driver creation
    Gateway::where('code', 'himalayan')->update(['enabled' => true]);
    $himalayan = $manager->createDriver('himalayan');
    expect($himalayan->checkoutRedirect())->toBeTrue();
});

it('returns checkout route correctly', function (): void {
    $manager = app(PaymentGatewayManager::class);
    $route = $manager->getCheckoutRoute(['amount_cents' => 1000, 'currency' => 'USD']);

    expect($route)->toContain('payment/checkout');
});
