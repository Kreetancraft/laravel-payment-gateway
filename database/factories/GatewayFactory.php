<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Kreetancraft\PaymentGateway\Gateways\HimalayanBankGateway;
use Kreetancraft\PaymentGateway\Gateways\StripeGateway;
use Kreetancraft\PaymentGateway\Models\Gateway;

/**
 * @extends Factory<Gateway>
 */
class GatewayFactory extends Factory
{
    protected $model = Gateway::class;

    public function definition(): array
    {
        return [
            'code' => 'stripe',
            'label' => 'Pay with Stripe',
            'icon' => 'https://js.stripe.com/v3/stripe-logo.svg',
            'enabled' => true,
            'class' => StripeGateway::class,
            'currencies' => ['USD', 'EUR', 'GBP', 'NPR'],
            'capabilities' => ['charge', 'refund', 'webhook', 'verify'],
            'checkout_redirect' => false,
            'supports_subscriptions' => false,
            'environment' => 'demo',
            'config_fields' => [],
            'credentials' => [
                'secret_key' => 'sk_test_mock',
                'publishable_key' => 'pk_test_mock',
                'webhook_secret' => 'whsec_test_mock',
            ],
        ];
    }

    public function enabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'enabled' => true,
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'enabled' => false,
        ]);
    }

    public function himalayan(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'himalayan',
            'label' => 'Himalayan Bank (2C2P PACO)',
            'icon' => 'https://www.himalayanbank.com/themes/himalayan/assets/ico/hbl-icon.png',
            'enabled' => true,
            'class' => HimalayanBankGateway::class,
            'currencies' => ['NPR', 'USD', 'THB'],
            'checkout_redirect' => true,
            'credentials' => [
                'office_id' => '9104137120',
                'api_key' => 'test_api_key',
                'encryption_key_id' => 'test_enc_key_id',
            ],
        ]);
    }
}
