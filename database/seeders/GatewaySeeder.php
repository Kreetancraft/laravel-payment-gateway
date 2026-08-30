<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Database\Seeders;

use Illuminate\Database\Seeder;
use Kreetancraft\PaymentGateway\Models\Gateway;
use Illuminate\Support\Facades\Crypt;

class GatewaySeeder extends Seeder
{
    public function run(): void
    {
        // Stripe Gateway
        Gateway::updateOrCreate(
            ['code' => 'stripe'],
            [
                'label' => 'Pay with Stripe',
                'icon' => 'https://js.stripe.com/v3/stripe-logo.svg',
                'enabled' => true,
                'class' => \Kreetancraft\PaymentGateway\Gateways\StripeGateway::class,
                'currencies' => ['USD', 'EUR', 'GBP', 'INR', 'NPR', 'AUD', 'CAD'],
                'capabilities' => ['charge', 'refund', 'webhook', 'verify'],
                'checkout_redirect' => false,
                'supports_subscriptions' => false,
                'environment' => 'demo',
                'config_fields' => [
                    [
                        'key' => 'secret_key',
                        'label' => 'Secret Key',
                        'type' => 'password',
                        'required' => true,
                        'description' => 'Stripe Secret API Key (sk_test_... or sk_live_...)',
                    ],
                    [
                        'key' => 'publishable_key',
                        'label' => 'Publishable Key',
                        'type' => 'text',
                        'required' => true,
                        'description' => 'Stripe Publishable Key (pk_test_... or pk_live_...)',
                    ],
                    [
                        'key' => 'webhook_secret',
                        'label' => 'Webhook Secret',
                        'type' => 'password',
                        'required' => true,
                        'description' => 'Stripe Webhook Signing Secret (whsec_...)',
                    ],
                ],
                'credentials' => [
                    'secret_key' => null,
                    'publishable_key' => null,
                    'webhook_secret' => null,
                ],
            ]
        );

        // Himalayan Bank (2C2P PACO) Gateway
        Gateway::updateOrCreate(
            ['code' => 'himalayan'],
            [
                'label' => 'Himalayan Bank (2C2P PACO)',
                'icon' => 'https://www.himalayanbank.com/themes/himalayan/assets/ico/hbl-icon.png',
                'enabled' => false,
                'class' => \Kreetancraft\PaymentGateway\Gateways\HimalayanBankGateway::class,
                'currencies' => ['NPR', 'USD', 'THB'],
                'capabilities' => ['charge', 'refund', 'webhook', 'verify'],
                'checkout_redirect' => true,
                'supports_subscriptions' => false,
                'environment' => 'demo',
                'config_fields' => [
                    [
                        'key' => 'office_id',
                        'label' => 'Office ID',
                        'type' => 'text',
                        'required' => true,
                        'description' => 'Office ID provided by Himalayan Bank (e.g., 9104137120)',
                    ],
                    [
                        'key' => 'api_key',
                        'label' => 'API Key',
                        'type' => 'password',
                        'required' => true,
                        'description' => 'API Key provided by Himalayan Bank',
                    ],
                    [
                        'key' => 'encryption_key_id',
                        'label' => 'Encryption Key ID',
                        'type' => 'text',
                        'required' => true,
                        'description' => 'Encryption Key ID provided by Himalayan Bank',
                    ],
                    [
                        'key' => 'merchant_signing_key_path',
                        'label' => 'Merchant Signing Key Path',
                        'type' => 'file',
                        'required' => true,
                        'description' => 'Path to merchant signing private key (PKCS#8 RSA private key)',
                    ],
                    [
                        'key' => 'merchant_decryption_key_path',
                        'label' => 'Merchant Decryption Key Path',
                        'type' => 'file',
                        'required' => true,
                        'description' => 'Path to merchant decryption private key (PKCS#8 RSA private key)',
                    ],
                    [
                        'key' => 'paco_encryption_public_key_path',
                        'label' => 'PACO Encryption Public Key Path',
                        'type' => 'file',
                        'required' => true,
                        'description' => 'Path to PACO encryption public key (RSA-OAEP)',
                    ],
                    [
                        'key' => 'paco_signing_public_key_path',
                        'label' => 'PACO Signing Public Key Path',
                        'type' => 'file',
                        'required' => true,
                        'description' => 'Path to PACO signing public key (PS256)',
                    ],
                    [
                        'key' => 'environment',
                        'label' => 'Environment',
                        'type' => 'select',
                        'options' => [
                            'demo' => 'UAT/Sandbox',
                            'production' => 'Production',
                        ],
                        'default' => 'demo',
                        'required' => true,
                    ],
                    [
                        'key' => 'currencies',
                        'label' => 'Supported Currencies',
                        'type' => 'multiselect',
                        'options' => [
                            'NPR' => 'Nepalese Rupee (NPR)',
                            'USD' => 'US Dollar (USD)',
                            'THB' => 'Thai Baht (THB)',
                        ],
                        'default' => ['NPR', 'USD'],
                    ],
                ],
                'credentials' => [
                    'office_id' => null,
                    'api_key' => null,
                    'encryption_key_id' => null,
                    'merchant_signing_key_path' => null,
                    'merchant_decryption_key_path' => null,
                    'paco_encryption_public_key_path' => null,
                    'paco_signing_public_key_path' => null,
                    'environment' => 'demo',
                    'currencies' => ['NPR', 'USD'],
                ],
            ]
        );
    }
}