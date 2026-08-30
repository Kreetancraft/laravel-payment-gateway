<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Payment Gateway Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration file defines the available payment gateways.
    | Actual credentials are stored in the database (payment_gateways table)
    | with encrypted sensitive fields. This file defines the gateway definitions
    | and default configurations.
    |
    */

    'gateways' => [
        'stripe' => [
            'class' => \Kreetancraft\PaymentGateway\Gateways\StripeGateway::class,
            'label' => 'Pay with Stripe',
            'icon' => 'https://js.stripe.com/v3/stripe-logo.svg',
            'currencies' => ['USD', 'EUR', 'GBP', 'INR', 'NPR', 'AUD', 'CAD'],
            'supports_subscriptions' => false,
            'checkout_redirect' => false,
            'capabilities' => ['charge', 'refund', 'webhook', 'verify'],
            'config_fields' => [
                'secret_key' => [
                    'label' => 'Secret Key',
                    'type' => 'password',
                    'required' => true,
                    'description' => 'Stripe Secret API Key (sk_test_... or sk_live_...)',
                ],
                'publishable_key' => [
                    'label' => 'Publishable Key',
                    'type' => 'text',
                    'required' => true,
                    'description' => 'Stripe Publishable Key (pk_test_... or pk_live_...)',
                ],
                'webhook_secret' => [
                    'label' => 'Webhook Secret',
                    'type' => 'password',
                    'required' => true,
                    'description' => 'Stripe Webhook Signing Secret (whsec_...)',
                ],
            ],
        ],

        'himalayan' => [
            'class' => \Kreetancraft\PaymentGateway\Gateways\HimalayanBankGateway::class,
            'label' => 'Himalayan Bank (2C2P PACO)',
            'icon' => 'https://www.himalayanbank.com/themes/himalayan/assets/ico/hbl-icon.png',
            'currencies' => ['NPR', 'USD', 'THB'],
            'supports_subscriptions' => false,
            'checkout_redirect' => true,
            'capabilities' => ['charge', 'refund', 'webhook', 'verify'],
            'config_fields' => [
                'office_id' => [
                    'label' => 'Office ID',
                    'type' => 'text',
                    'required' => true,
                    'description' => 'Office ID provided by Himalayan Bank (e.g., 9104137120)',
                ],
                'api_key' => [
                    'label' => 'API Key',
                    'type' => 'password',
                    'required' => true,
                    'description' => 'API Key provided by Himalayan Bank',
                ],
                'encryption_key_id' => [
                    'label' => 'Encryption Key ID',
                    'type' => 'text',
                    'required' => true,
                    'description' => 'Encryption Key ID provided by Himalayan Bank',
                ],
                'merchant_signing_key' => [
                    'label' => 'Merchant Signing Key',
                    'type' => 'textarea',
                    'required' => true,
                    'description' => 'Paste PEM private key (or path like storage/app/private/hbl/merchant_signing.key — will be read from private storage)',
                ],
                'merchant_decryption_key' => [
                    'label' => 'Merchant Decryption Key',
                    'type' => 'textarea',
                    'required' => true,
                    'description' => 'Paste PEM private key (or private storage path)',
                ],
                'paco_encryption_public_key' => [
                    'label' => 'PACO Encryption Public Key',
                    'type' => 'textarea',
                    'required' => true,
                    'description' => 'Paste PEM public key (RSA-OAEP) or private storage path',
                ],
                'paco_signing_public_key' => [
                    'label' => 'PACO Signing Public Key',
                    'type' => 'textarea',
                    'required' => true,
                    'description' => 'Paste PEM public key (PS256) or private storage path',
                ],
                'environment' => [
                    'label' => 'Environment',
                    'type' => 'select',
                    'options' => [
                        'demo' => 'UAT/Sandbox',
                        'production' => 'Production',
                    ],
                    'default' => 'demo',
                    'required' => true,
                ],
                'currencies' => [
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
        ],
    ],

    'routes' => [
        'prefix' => 'payment',
        'middleware' => ['web'],
        'names' => [
            'dashboard' => '/',
            'checkout' => 'payment.checkout',
            'success' => 'payment.success',
            'cancel' => 'payment.cancel',
            'security_edit' => 'payment.security.edit',
        ],
    ],

    'webhook' => [
        'secret' => env('PAYMENT_GATEWAY_WEBHOOK_SECRET'),
        'verify_signature' => true,
    ],

    'features' => [
        'refunds' => true,
        'redirects' => true,
        'webhooks' => true,
        'verify' => true,
    ],

    'layouts' => [
        'admin' => 'layouts.app',
        'auth' => 'layouts.auth',
    ],
];