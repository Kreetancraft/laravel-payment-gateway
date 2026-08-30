<?php

declare(strict_types=1);

use Kreetancraft\PaymentGateway\Gateways\HimalayanBankGateway;
use Kreetancraft\PaymentGateway\Gateways\StripeGateway;

return [
    /*
    |--------------------------------------------------------------------------
    | Default Payment Gateway
    |--------------------------------------------------------------------------
    |
    | The default payment gateway driver to use when none is explicitly requested.
    | If left null, the package automatically resolves the first enabled gateway.
    |
    */
    'default' => env('PAYMENT_GATEWAY_DEFAULT', null),

    /*
    |--------------------------------------------------------------------------
    | Database Encryption Secret Key
    |--------------------------------------------------------------------------
    |
    | Dedicated key used to encrypt all gateway credentials and private keys
    | in the database. If your database is compromised, all sensitive tokens
    | remain protected without this secret. Falls back to APP_KEY if empty.
    |
    */
    'encryption' => [
        'key' => env('PAYMENT_GATEWAY_ENCRYPTION_KEY', env('PAYMENT_GATEWAY_SECRET', null)),
        'cipher' => env('PAYMENT_GATEWAY_CIPHER', 'AES-256-CBC'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Gateways Definition
    |--------------------------------------------------------------------------
    |
    | Gateways definitions, supported currencies, and configuration field schemas.
    | Actual live credentials and enabled states are stored securely in the
    | `payment_gateways` database table and configured via the admin UI.
    |
    */
    'gateways' => [
        'stripe' => [
            'class' => StripeGateway::class,
            'label' => 'Pay with Stripe',
            'icon' => 'https://cdn.brandfetch.io/idxAg10C0L/theme/dark/logo.svg?c=1dxbfHSJFAPEGdCLU4o5B',
            'currencies' => ['USD', 'EUR', 'GBP', 'INR', 'NPR', 'AUD', 'CAD'],
            'supports_subscriptions' => false,
            'checkout_redirect' => false,
            'capabilities' => ['charge', 'refund', 'webhook', 'verify'],
            'config_fields' => [
                'secret_key' => [
                    'key' => 'secret_key',
                    'label' => 'Secret Key',
                    'type' => 'password',
                    'required' => true,
                    'description' => 'Stripe Secret API Key (sk_test_... or sk_live_...)',
                ],
                'publishable_key' => [
                    'key' => 'publishable_key',
                    'label' => 'Publishable Key',
                    'type' => 'text',
                    'required' => true,
                    'description' => 'Stripe Publishable Key (pk_test_... or pk_live_...)',
                ],
                'webhook_secret' => [
                    'key' => 'webhook_secret',
                    'label' => 'Webhook Secret',
                    'type' => 'password',
                    'required' => false,
                    'description' => 'Stripe Webhook Signing Secret (whsec_...)',
                ],
            ],
        ],

        'himalayan' => [
            'class' => HimalayanBankGateway::class,
            'label' => 'Himalayan Bank (2C2P PACO)',
            'icon' => 'https://cdn.brandfetch.io/id2P9wxe6-/w/283/h/283/theme/dark/icon.jpeg?c=1dxbfHSJFAPEGdCLU4o5B',
            'currencies' => ['NPR', 'USD', 'THB'],
            'supports_subscriptions' => false,
            'checkout_redirect' => true,
            'capabilities' => ['charge', 'refund', 'webhook', 'verify'],
            'config_fields' => [
                'office_id' => [
                    'key' => 'office_id',
                    'label' => 'Office ID',
                    'type' => 'text',
                    'required' => true,
                    'description' => 'Office ID provided by Himalayan Bank (e.g., 9104137120)',
                ],
                'api_key' => [
                    'key' => 'api_key',
                    'label' => 'API Key',
                    'type' => 'password',
                    'required' => true,
                    'description' => 'API Key provided by Himalayan Bank',
                ],
                'encryption_key_id' => [
                    'key' => 'encryption_key_id',
                    'label' => 'Encryption Key ID',
                    'type' => 'text',
                    'required' => true,
                    'description' => 'Encryption Key ID provided by Himalayan Bank',
                ],
                'merchant_signing_key' => [
                    'key' => 'merchant_signing_key',
                    'label' => 'Merchant Signing Key (PEM)',
                    'type' => 'textarea',
                    'required' => true,
                    'description' => 'Paste PKCS#8 RSA private key PEM (stored encrypted in database)',
                ],
                'merchant_decryption_key' => [
                    'key' => 'merchant_decryption_key',
                    'label' => 'Merchant Decryption Key (PEM)',
                    'type' => 'textarea',
                    'required' => true,
                    'description' => 'Paste PKCS#8 RSA private key PEM (stored encrypted in database)',
                ],
                'paco_encryption_public_key' => [
                    'key' => 'paco_encryption_public_key',
                    'label' => 'PACO Encryption Public Key (PEM)',
                    'type' => 'textarea',
                    'required' => true,
                    'description' => 'Paste RSA-OAEP public key PEM (stored encrypted in database)',
                ],
                'paco_signing_public_key' => [
                    'key' => 'paco_signing_public_key',
                    'label' => 'PACO Signing Public Key (PEM)',
                    'type' => 'textarea',
                    'required' => true,
                    'description' => 'Paste PS256 public key PEM (stored encrypted in database)',
                ],
                'environment' => [
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
            ],
            'base_url' => env('HBL_BASE_URL', 'https://core.demo-paco.2c2p.com/'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes Configuration
    |--------------------------------------------------------------------------
    */
    'routes' => [
        'register' => true,
        'prefix' => 'payment',
        'middleware' => ['web'],
        'register_api' => true,
        'api_prefix' => 'api/v1/payment',
        'api_middleware' => ['api'],
        'names' => [
            'checkout' => 'payment.checkout',
            'success' => 'payment.success',
            'cancel' => 'payment.cancel',
            'gateways' => 'admin.payment.gateways',
            'gateways_edit' => 'admin.payment.gateways.edit',
            'coupons' => 'admin.payment.coupons',
            'coupons_create' => 'admin.payment.coupons.create',
            'coupons_edit' => 'admin.payment.coupons.edit',
            'coupons_show' => 'admin.payment.coupons.show',
            'transactions' => 'admin.payment.transactions',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    */
    'webhook' => [
        'secret' => env('PAYMENT_GATEWAY_WEBHOOK_SECRET'),
        'verify_signature' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Navigation Group
    |--------------------------------------------------------------------------
    */
    'navigation' => [
        'group' => 'Payments',
    ],
];
