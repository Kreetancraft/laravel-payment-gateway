<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Tests\Unit;

use Kreetancraft\PaymentGateway\Models\GatewayConfig;
use Kreetancraft\PaymentGateway\Contracts\GatewayResolver;
use Kreetancraft\PaymentGateway\PaymentGatewayManager;

uses(\Kreetancraft\PaymentGateway\Tests\TestCase::class)
    ->in('Unit');

beforeEach(function () {
    $this->app->make(\Kreetancraft\PaymentGateway\Providers\PaymentGatewayServiceProvider::class);
    config()->set('payment-gateway.gateways', [
        'stripe' => [
            'class' => \Kreetancraft\PaymentGateway\Gateways\StripeGateway::class,
            'label' => 'Stripe',
            'enabled' => true,
            'currencies' => ['USD', 'EUR'],
            'capabilities' => ['charge', 'refund', 'webhook', 'verify'],
        ],
        'himalayan' => [
            'class' => \Kreetancraft\PaymentGateway\Gateways\HimalayanBankGateway::class,
            'label' => 'Himalayan Bank',
            'enabled' => false,
            'currencies' => ['NPR', 'USD'],
            'capabilities' => ['charge', 'refund', 'webhook', 'verify'],
        ],
    ]);
});

it('filters enabled gateways only', function () {
    $resolver = app(GatewayResolver::class);
    $enabled = $resolver->getEnabledGateways();
    
    expect($enabled)->toContain('stripe')
        ->not->toContain('himalayan');
});

it('returns default driver correctly', function () {
    $manager = app(\Kreetancraft\PaymentGateway\PaymentGatewayManager::class);
    $driver = $manager->getDefaultDriver();
    
    expect($driver)->toBe('stripe');
});

it('resolves gateway class correctly', function () {
    $manager = app(\Kreetancraft\PaymentGateway\PaymentGatewayManager::class);
    $driver = $manager->createDriver('stripe');
    
    expect($driver)->toBeInstanceOf(\Kreetancraft\PaymentGateway\Gateways\StripeGateway::class);
});

it('throws on unknown gateway', function () {
    $manager = app(\Kreetancraft\PaymentGateway\PaymentGatewayManager::class);
    
    $manager->createDriver('nonexistent')
        ->expectException(\InvalidArgumentException::class);
});

it('filters enabled gateways from config', function () {
    $resolver = app(GatewayResolver::class);
    $enabled = $resolver->getEnabledGateways();
    
    expect($enabled)->toHaveCount(1)
        ->and($enabled[0])->toBe('stripe');
});

it('checks currency support', function () {
    $manager = app(\Kreetancraft\PaymentGateway\PaymentGatewayManager::class);
    $driver = $manager->createDriver('stripe');
    
    expect($driver->supportsCurrency('USD'))->toBeTrue()
        ->and($driver->supportsCurrency('XYZ'))->toBeFalse();
});

it('returns correct gateway label', function () {
    $manager = app(\Kreetancraft\PaymentGateway\PaymentGatewayManager::class);
    $driver = $manager->createDriver('stripe');
    
    expect($driver->getLabel())->toBe('Pay with Stripe');
});

it('returns correct gateway icon', function () {
    $manager = app(\Kreetancraft\PaymentGateway\PaymentGatewayManager::class);
    $driver = $manager->createDriver('stripe');
    
    expect($driver->getIcon())->toBe('https://js.stripe.com/v3/stripe-logo.svg');
});

it('checks checkout redirect correctly', function () {
    $manager = app(\Kreetancraft\PaymentGateway\PaymentGatewayManager::class);
    
    $stripe = $manager->createDriver('stripe');
    expect($stripe->checkoutRedirect())->toBeFalse();
    
    $himalayan = $manager->createDriver('himalayan');
    expect($himalayan->checkoutRedirect())->toBeTrue();
});

it('returns checkout route correctly', function () {
    $manager = app(\Kreetancraft\PaymentGateway\PaymentGatewayManager::class);
    
    $route = $manager->getCheckoutRoute(['amount_cents' => 1000, 'currency' => 'USD']);
    
    expect($route)->toContain('payment.checkout');
});