# kreetancraft/laravel-payment-gateway

Complete payment gateway for Laravel — **Livewire 4 + Flux UI**, **Laravel Fortify** (2FA, passkeys), **roles & permissions** (spatie/laravel-permission), **coupons/discounts**, **impersonation**, and **login history** with GeoIP.

Standalone package — no `nwidart/laravel-modules`. Drops into any Laravel 12/13 app (including `laravel new --using=livewire` starter kit).

> **Flux is a hard dependency.** All admin views use `<flux:*>` 200+ times. Consumers must have `livewire/flux` installed (paid).

## Requirements

- PHP `^8.2`, Laravel `^12|^13`
- `livewire/livewire ^4`, `livewire/flux ^2`, `laravel/fortify ^1.37`
- `spatie/laravel-permission ^8`, `spatie/laravel-data ^4`, `spatie/laravel-query-builder ^7`
- `lab404/laravel-impersonate ^1.7`, `torann/geoip ^3.0` (required, not optional)

## Installation

```bash
composer require kreetancraft/laravel-payment-gateway
php artisan vendor:publish --tag=payment-gateway-config
php artisan vendor:publish --tag=payment-gateway-migrations
php artisan migrate
php artisan payment-gateway:super-admin
php artisan payment-gateway:sync-permissions
```

### Point auth to the package User (optional)

In `config/auth.php`:

```php
'providers' => [
    'users' => [
        'driver' => 'eloquent',
        'model' => Kreetancraft\PaymentGateway\Models\User::class,
    ],
],
```

Or extend it in your app:

```php
class User extends \Kreetancraft\PaymentManagement\Models\User {}
```

## Features

- **Users:** CRUD, soft-delete, invitation flow (`/set-password/{token}` with 48h expiry + `throttle:6,1`), active/inactive, `enforce_2fa`
- **Roles & Permissions:** Livewire `ManageRoles` / `CreateRole` / `EditRole` + `CreatePermission` / `DeletePermission`
- **Auth:** Fortify views at `site-settings::auth.*`, 2FA challenge, passkeys, password reset, email verification
- **Login history:** `user_login_histories` table, `RecordUserLogin` listener with GeoIP enrichment
- **Impersonation:** `Route::impersonate()` via `lab404/laravel-impersonate`, super-admin only (`canImpersonate` / `canBeImpersonated`)
- **UI:** 8 Livewire components, Flux tables/badges/modals, layouts `site-settings::layouts.app` / `auth`
- **Routes:** `routes/web.php` (`admin/users`, `admin/roles`, `set-password/{token}`, `Route::impersonate()` gated)
- **Commands:** `user-management:super-admin`, `user-management:sync-permissions`

## Coupons & Discounts (New!)

The package includes a **complete coupon/discount system** combining the best features from:
- **binafy/laravel-discount** (52★) - stacking, buy-x-get-y, tiered, free shipping
- **pixellair/laravel-discount-system** - simple coupons, prefixes, per-user limits
- **discountify** - conditions, min_order_amount, model attachments, time windows

### Coupon Features

| Feature | Description |
|---------|-------------|
| **Types** | Percentage, Fixed, Buy X Get Y, Tiered, Free Shipping |
| **Stacking** | Smart: max savings wins, free shipping always stacks |
| **Conditions** | min_order_amount, model attachments, time windows, user whitelist, custom conditions |
| **Coupon Codes** | Prefixed generation (T=time, A=amount, P=percentage, F=fixed, B=buy_x_get_y, T=tiered, F=free_shipping) |
| **Stacking** | Smart: max savings wins, free shipping always stacks |
| **Conditions** | min_order_amount, model attachments, time windows, user whitelist, custom conditions |
| **Code Generation** | Prefixed: T=time, A=amount, P=percentage, F=fixed, B=buy_x_get_y, T=tiered, F=free_shipping |
| **Stacking** | Max savings wins, free shipping always stacks |
| **Limits** | Total usage, per-user, user whitelist, time windows, min order |
| **Code Gen** | `php artisan coupon:generate --prefix=SAVE --count=100` |

### Coupon Types

| Type | Description |
|------|-------------|
| `percentage` | X% off (e.g., 20% off) |
| `fixed` | Fixed amount off (e.g., $10 off) |
| `buy_x_get_y` | Buy X get Y free (e.g., buy 2 get 1 free) |
| `tiered` | Tiered discounts (spend $100 → 5%, $500 → 10%) |
| `free_shipping` | Free shipping (stacks on top of monetary discounts) |

### Coupon Features

| Feature | Description |
|---------|-------------|
| **Stacking** | Smart: max savings wins, free shipping always stacks |
| **Conditions** | min_order_amount, model attachments, time windows, user whitelist, custom conditions |
| **Coupon Codes** | Prefixed generation (T=time, A=amount, P=percentage, F=fixed, B=buy_x_get_y, T=tiered, F=free_shipping) |
| **Stacking** | Smart: max savings wins, free shipping always stacks |
| **Limits** | Total usage, per-user, user whitelist, time windows, min order |
| **Code Gen** | `php artisan coupon:generate --prefix=SAVE --count=100` |

## Configuration

Publish config:

```bash
php artisan vendor:publish --tag=payment-gateway-config
```

Key config options in `config/payment-gateway.php`:

```php
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
            'secret_key' => ['label' => 'Secret Key', 'type' => 'password', 'required' => true],
            'publishable_key' => ['label' => 'Publishable Key', 'type' => 'text', 'required' => true],
            'webhook_secret' => ['label' => 'Webhook Secret', 'type' => 'password', 'required' => true],
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
            'office_id' => ['label' => 'Office ID', 'type' => 'text', 'required' => true],
            'api_key' => ['label' => 'API Key', 'type' => 'password', 'required' => true],
            'encryption_key_id' => ['label' => 'Encryption Key ID', 'type' => 'text', 'required' => true],
            'merchant_signing_key_path' => ['label' => 'Merchant Signing Key Path', 'type' => 'file', 'required' => true],
            'merchant_decryption_key_path' => ['label' => 'Merchant Decryption Key Path', 'type' => 'file', 'required' => true],
            'paco_encryption_public_key_path' => ['label' => 'PACO Encryption Public Key Path', 'type' => 'file', 'required' => true],
            'paco_signing_public_key_path' => ['label' => 'PACO Signing Public Key Path', 'type' => 'file', 'required' => true],
            'environment' => ['label' => 'Environment', 'type' => 'select', 'options' => ['demo' => 'UAT', 'production' => 'Production']],
            'currencies' => ['label' => 'Supported Currencies', 'type' => 'multiselect', 'options' => ['NPR' => 'NPR', 'USD' => 'USD', 'THB' => 'THB']],
        ],
    ],
],
```

## Routes

```bash
# Web routes (prefix: payment/)
GET  /payment/checkout/{gateway?}     # payment.checkout
GET  /payment/choose                  # payment.choose
GET  /payment/success                 # payment.success
GET  /payment/cancel                  # payment.cancel
GET  /payment/coupons                 # coupons.list
POST /payment/coupons/validate        # coupons.validate
POST /payment/coupons/apply           # coupons.apply

# API routes
POST   /api/v1/payment/checkout       # api.payment.checkout
POST   /api/v1/payment/verify         # api.payment.verify
POST   /api/v1/payment/refund         # api.payment.refund
GET    /api/v1/payment/gateways       # api.payment.gateways
POST   /api/v1/payment/webhook/{gateway}  # payment.webhook

# Coupon API
GET    /api/v1/payment/coupons           # coupons.list
POST   /api/v1/payment/coupons/validate  # coupons.validate
POST   /api/v1/payment/coupons/apply     # coupons.apply
```

## Admin UI

- **Manage Gateways**: `/admin/payment-gateways` (Livewire + Flux)
- **Manage Coupons**: `/admin/coupons` (Livewire + Flux)
- **Manage Users**: `/admin/users` (from user-management)

## Coupon API

```bash
# Validate coupon
POST /api/v1/payment/coupons/validate
{
    "code": "SAVE20",
    "amount_cents": 10000,
    "currency": "USD"
}

# Apply coupon
POST /api/v1/payment/coupons/apply
{
    "code": "SAVE20",
    "amount_cents": 10000,
    "currency": "USD"
}

# Response
{
    "success": true,
    "discount_cents": 2000,
    "final_amount_cents": 8000,
    "coupon": { "code": "SAVE20", "label": "20% Off", "type": "percentage", "value": 20 },
    "has_free_shipping": false
}
```

## Webhook Redemption

Coupons are automatically redeemed on successful payment webhook:

```php
// In your payment webhook handler
$result = HandleWebhookAction::run($gateway, $payload, $headers);
// Automatically redeems applied coupons via CouponService::redeem()
```

## Coupon Management UI

Visit `/admin/coupons` for full CRUD:
- Create/edit/delete coupons
- Configure all discount types
- Set usage limits, expiry, user restrictions
- Configure conditions (min order, currencies, time windows, model attachments)
- Set stacking rules, free shipping

## Coupon Code Generation

```bash
# Generate single code
php artisan coupon:generate

# With prefix
php artisan coupon:generate --prefix=SAVE

# Generate multiple
php artisan coupon:generate --prefix=SAVE --count=100
# Output: SAVE-ABC123XY, SAVE-DEF456ZW, ...
```

## Testing

```bash
# Run all tests
vendor/bin/pest

# Run specific test
vendor/bin/pest tests/Feature/CouponTest.php
```

## Packagist Publishing

```bash
git tag 0.1.0 -m "Initial release"
git push origin main --tags
# Submit at https://packagist.org/packages/submit
```

## License

MIT