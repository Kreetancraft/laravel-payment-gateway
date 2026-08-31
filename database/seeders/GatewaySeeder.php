<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Database\Seeders;

use Illuminate\Database\Seeder;
use Kreetancraft\PaymentGateway\Gateways\HimalayanBankGateway;
use Kreetancraft\PaymentGateway\Gateways\StripeGateway;
use Kreetancraft\PaymentGateway\Models\Gateway;

class GatewaySeeder extends Seeder
{
    public function run(): void
    {
        // Stripe Gateway
        Gateway::updateOrCreate(
            ['code' => 'stripe'],
            [
                'label' => 'Pay with Stripe',
                'icon' => 'https://cdn.brandfetch.io/idxAg10C0L/theme/dark/logo.svg?c=1dxbfHSJFAPEGdCLU4o5B',
                'enabled' => true,
                'class' => StripeGateway::class,
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
                        'required' => false,
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
                'icon' => 'https://cdn.brandfetch.io/id2P9wxe6-/w/283/h/283/theme/dark/icon.jpeg?c=1dxbfHSJFAPEGdCLU4o5B',
                'enabled' => false,
                'class' => HimalayanBankGateway::class,
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
                        'description' => 'Office ID provided by Himalayan Bank (e.g. 9104137120)',
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
                        'key' => 'merchant_signing_key',
                        'label' => 'Merchant Signing Key (PEM)',
                        'type' => 'textarea',
                        'required' => true,
                        'description' => 'Paste PKCS#8 RSA private key PEM (stored encrypted in database)',
                    ],
                    [
                        'key' => 'merchant_decryption_key',
                        'label' => 'Merchant Decryption Key (PEM)',
                        'type' => 'textarea',
                        'required' => true,
                        'description' => 'Paste PKCS#8 RSA private key PEM (stored encrypted in database)',
                    ],
                    [
                        'key' => 'paco_encryption_public_key',
                        'label' => 'PACO Encryption Public Key (PEM)',
                        'type' => 'textarea',
                        'required' => true,
                        'description' => 'Paste RSA-OAEP public key PEM (stored encrypted in database)',
                    ],
                    [
                        'key' => 'paco_signing_public_key',
                        'label' => 'PACO Signing Public Key (PEM)',
                        'type' => 'textarea',
                        'required' => true,
                        'description' => 'Paste PS256 public key PEM (stored encrypted in database)',
                    ],
                    [
                        'key' => 'environment',
                        'label' => 'Environment',
                        'type' => 'select',
                        'options' => [
                            'demo' => 'UAT / Sandbox',
                            'production' => 'Production',
                        ],
                        'default' => 'demo',
                        'required' => true,
                    ],
                    [
                        'key' => 'request_3ds',
                        'label' => 'Enable 3D Secure',
                        'type' => 'checkbox',
                        'default' => true,
                        'required' => false,
                        'description' => 'Request 3DS flag Y/N — turn off for test mode (matches WP Enable/Disable 3D Secure)',
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
                    'merchant_signing_key' => null,
                    'merchant_decryption_key' => null,
                    'paco_encryption_public_key' => null,
                    'paco_signing_public_key' => null,
                    'environment' => 'demo',
                    'request_3ds' => true,
                    'currencies' => ['NPR', 'USD'],
                ],
            ]
        );
    }
}
