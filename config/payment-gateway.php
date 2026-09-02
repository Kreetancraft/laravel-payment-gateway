<?php

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

            // Pin the Stripe API version deliberately, or leave null to take
            // whatever the installed SDK defaults to. This used to be hardcoded
            // to '2026-08-26.dahlia', which matches no documented release — and
            // an unknown version fails every call. Verify any value here against
            // the account before shipping it.
            'api_version' => env('STRIPE_API_VERSION'),
            'label' => 'Pay with Stripe',
            'icon' => 'https://cdn.brandfetch.io/idxAg10C0L/theme/dark/logo.svg?c=1dxbfHSJFAPEGdCLU4o5B',
            'currencies' => ['USD', 'EUR', 'GBP', 'INR', 'NPR', 'AUD', 'CAD'],
            'supports_subscriptions' => false,
            // Stripe hosts the payment page: the buyer leaves for
            // checkout.stripe.com and returns to success_url. No card field is
            // rendered by this package.
            'checkout_redirect' => true,
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
                    'description' => 'Office ID provided by Himalayan Bank (e.g., **************)',
                ],
                'api_key' => [
                    'key' => 'api_key',
                    'label' => 'API Key',
                    'type' => 'password',
                    'required' => true,
                    'description' => 'API Key provided by Himalayan Bank (e.g., **************)',
                ],
                'encryption_key_id' => [
                    'key' => 'encryption_key_id',
                    'label' => 'Encryption Key ID',
                    'type' => 'text',
                    'required' => true,
                    'description' => 'Encryption Key ID provided by Himalayan Bank (e.g., **************)',
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
                'request_3ds' => [
                    'key' => 'request_3ds',
                    'label' => 'Enable 3D Secure',
                    'type' => 'checkbox',
                    'default' => true,
                    'required' => false,
                    'description' => 'Request 3DS flag Y/N — turn off for test mode (matches WP Enable/Disable 3D Secure)',
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
    /*
    |--------------------------------------------------------------------------
    | Payables
    |--------------------------------------------------------------------------
    |
    | What may be paid for. A checkout names one of these plus an id; the amount
    | and currency are read off the model, never taken from the request.
    |
    | Keys are the public alias a client sends, values the model class, which
    | must implement Kreetancraft\PaymentGateway\Contracts\Payable. An alias
    | not listed here is refused — so a caller cannot point checkout at an
    | arbitrary model and have it charged.
    |
    |     'invoice' => \App\Models\Invoice::class,
    |     'booking' => \App\Models\Booking::class,
    |
    */
    'payables' => [],

    /*
    |--------------------------------------------------------------------------
    | Stylesheet for the buyer-facing pages
    |--------------------------------------------------------------------------
    |
    | The package's own layout (success, failed, cancel) emits `@vite` only when
    | the host has actually built `resources/css/app.css`. If yours does not —
    | different entry points, no build step, a CDN — name a view here and it is
    | included in the head instead. Null means nothing extra is emitted.
    |
    */

    'assets_view' => null,

    /*
    |--------------------------------------------------------------------------
    | Layouts
    |--------------------------------------------------------------------------
    |
    | This package ships no layout; its screens render into yours. Leave these
    | null and it tries the usual conventions — components.layouts.app first.
    | Give checkout its own if you do not want admin chrome around a payment
    | page a buyer sees.
    |
    */
    'layouts' => [
        'admin' => null,
        'checkout' => null,
    ],

    'routes' => [
        'register' => env('PAYMENT_GATEWAY_REGISTER_ROUTES', true),
        'register_ui' => env('PAYMENT_GATEWAY_REGISTER_UI', true), // Set false for headless API setups
        'register_admin' => env('PAYMENT_GATEWAY_REGISTER_ADMIN', true),
        'register_api' => env('PAYMENT_GATEWAY_REGISTER_API', true),
        'prefix' => env('PAYMENT_GATEWAY_ROUTE_PREFIX', 'payment'),
        'middleware' => ['web'],

        // Endpoints that read payment data back or hand out discount codes.
        // Staff work, not buyer work. Point this at whatever guard your API
        // uses — 'auth:sanctum' for a token API, 'auth' for a session one.
        'protected_middleware' => ['auth'],
        'api_prefix' => env('PAYMENT_GATEWAY_API_PREFIX', 'api/v1/payment'),
        'api_middleware' => ['api'],
        'names' => [
            'checkout' => 'payment.checkout',
            'success' => 'payment.success',
            'cancel' => 'payment.cancel',
            'failed' => 'payment.failed',
            'gateways' => 'admin.payment.gateways',
            'gateways_edit' => 'admin.payment.gateways.edit',
            'coupons' => 'admin.payment.coupons',
            'coupons_create' => 'admin.payment.coupons.create',
            'coupons_edit' => 'admin.payment.coupons.edit',
            'coupons_show' => 'admin.payment.coupons.show',
            'transactions' => 'admin.payment.transactions',
        ],
        'redirect_urls' => [
            'success' => env('PAYMENT_GATEWAY_SUCCESS_URL', null), // Custom success redirect URL or route
            'cancel' => env('PAYMENT_GATEWAY_CANCEL_URL', null),   // Custom cancel redirect URL or route
            'failed' => env('PAYMENT_GATEWAY_FAILED_URL', null),   // Custom failed redirect URL or route
            'webhook' => env('PAYMENT_GATEWAY_WEBHOOK_URL', null), // Custom webhook URL
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
