# Laravel Payment Gateway

Unified payments for Laravel — **Stripe** and **Himalayan Bank (2C2P PACO)**, encrypted database
credential management, a provider manager UI, full coupon system with smart stacking, refunds, and
automated webhooks. Livewire 4 + Flux UI.

Ships no layout, no hardcoded styling, no user model and no external dependencies — it renders into
your application and works with whatever you already have.

## Design decisions worth knowing before you install

**Credentials and configurations are encrypted and stored in the database.** You do not need to edit
config files or restart workers to rotate keys or enable a gateway. All sensitive credentials
(Stripe secrets, HBL private keys, API tokens) are encrypted with a dedicated `.env` secret key
(`PAYMENT_GATEWAY_ENCRYPTION_KEY`). Even if your database is dumped or compromised, your gateway
credentials remain protected.

**It does not care which user model you have.** Payments and coupon usages resolve their customer
through `config('auth.providers.users.model')`; every policy type-hints `Authenticatable`.

**It names no permission of its own.** Its screens ask standard authorization questions and its
policies answer them. Until permissions exist anywhere in the app they are open, so it works on a
bare install rather than failing closed.

**It is architected around SOLID principles and easily extensible.** Every payment driver implements
`PaymentGateway` contract (extending `AbstractGateway`). To add PayPal, Esewa, Khalti, or any other
provider, simply create a new class implementing the interface and register it in the database.

**Flux is a hard dependency.** The admin management views use `<flux:*>` throughout.

## Installation

```bash
composer require kreetancraft/laravel-payment-gateway
```

```bash
php artisan migrate
```

```bash
php artisan db:seed --class="Kreetancraft\PaymentGateway\Database\Seeders\GatewaySeeder"
```

### Let Tailwind see this package

Required. Tailwind v4 generates only the classes it finds by scanning files, and it does not scan
`vendor/`. In `resources/css/app.css`:

```css
@source '../../vendor/kreetancraft/laravel-payment-gateway/resources/views';
```

Skipping it fails confusingly rather than loudly — classes shared with your own views still work
and only the ones unique to this package go missing.

## Database Encryption & Security

To protect gateway secrets if your database is ever compromised, set a dedicated encryption key in
your `.env`:

```env
PAYMENT_GATEWAY_ENCRYPTION_KEY=base64:YOUR_32_BYTE_BASE64_KEY_HERE
```

_(If not set, the package seamlessly falls back to your standard `APP_KEY`)_

All API keys, secrets, and private RSA keys entered in the admin interface are encrypted on the fly
before writing to `payment_gateways.credentials` and decrypted only in memory when preparing an API payload.
**No files, paths, or `storage/` directory permissions are required** — everything lives securely in your database.

## Gateway Capabilities

| Gateway            | Integration                | Flow                | Currencies                        | Capabilities                         |
| ------------------ | -------------------------- | ------------------- | --------------------------------- | ------------------------------------ |
| **Stripe**         | Stripe SDK / PaymentIntent | Embedded / Elements | USD, EUR, GBP, AUD, CAD, NPR, INR | Charge, Refund, Webhook, Verify      |
| **Himalayan Bank** | 2C2P PACO (JOSE/JWE)       | Hosted 3DS Redirect | NPR, USD, THB                     | Charge, Void/Refund, Webhook, Verify |

### Adding Custom Payment Gateways

Any new gateway can be added without altering the package core (Open/Closed principle):

1. Create a class extending `AbstractGateway`:

```php
namespace App\Gateways;

use Kreetancraft\PaymentGateway\Gateways\AbstractGateway;
use Kreetancraft\PaymentGateway\Data\PaymentResult;
use Kreetancraft\PaymentGateway\Data\RefundResult;
use Kreetancraft\PaymentGateway\Data\VerificationResult;
use Kreetancraft\PaymentGateway\Data\WebhookResult;

class MyCustomGateway extends AbstractGateway
{
    public function charge(array $data): PaymentResult { ... }
    public function refund(string $transactionId, float $amount): RefundResult { ... }
    public function verify(array $data): VerificationResult { ... }
    public function webhook(array $payload): WebhookResult { ... }
    public function getCode(): string { return 'custom'; }
    public function getLabel(): string { return 'Custom Gateway'; }
    public function checkoutRedirect(): bool { return true; }
}
```

2. Register the gateway in the database via the admin UI (`/payment/gateways`) or a migration.

## The Provider Manager UI

The package provides full Livewire 4 + Flux UI management screens for configuring gateways and
coupons:

```blade
<!-- Mount directly in any Blade view or route -->
<livewire:payment.gateways />
<livewire:payment.coupons />
<livewire:payment.checkout />
```

Available admin routes (customizable in `config/payment-gateway.php`):

- `GET /payment/gateways` — Manage gateway credentials, toggle status, configure environments
- `GET /payment/manage-coupons` — Create, edit, and track coupon usages
- `GET /payment/checkout` — Hosted customer checkout with coupon input and gateway selection

## Coupons & Smart Stacking

A comprehensive discount engine with multiple coupon types and intelligent stacking algorithms:

| Type            | Calculation            | Behavior                                        |
| --------------- | ---------------------- | ----------------------------------------------- |
| `percentage`    | `amount * value / 100` | Percentage discount with optional maximum cap   |
| `fixed`         | `min(value, amount)`   | Fixed monetary deduction in cents               |
| `buy_x_get_y`   | Item-level computation | Quantity-based discount                         |
| `tiered`        | Tier-threshold rules   | Incremental discount based on total order size  |
| `free_shipping` | Shipping waiver        | Non-monetary discount that always stacks on top |

### Stacking Logic

- **Monetary Coupons**: Evaluates combinations to maximize customer savings without exceeding order total.
- **Free Shipping**: Always stacks on top of any monetary discount.
- **Restrictions**: Enforces minimum order amount, per-user limits, total limits, expiry dates, and user ID whitelists.

## Permissions

Every policy declares a subject, so with
[kreetancraft/laravel-user-management](https://github.com/Kreetancraft/laravel-user-management)
installed one command creates all of them:

```bash
php artisan user-management:sync-permissions
```

| Subject   | Abilities                                         |
| --------- | ------------------------------------------------- |
| `payment` | viewAny, view, create, update, delete, **refund** |
| `gateway` | viewAny, view, update, **toggle**                 |
| `coupon`  | viewAny, view, create, update, delete             |

**Gateways**, **Coupons**, and **Transactions** links automatically contribute themselves to the
admin sidebar through container tags (`payment.navigation.items`).

## Public & Private API

The package provides API routes for headless or mobile applications:

```
POST /api/v1/payment/checkout             Initiate charge (returns client_secret or redirect URL)
POST /api/v1/payment/verify               Verify transaction status with gateway
POST /api/v1/payment/refund               Process full or partial refund
GET  /api/v1/payment/gateways             List all enabled gateways and supported currencies
POST /api/v1/payment/webhook/{gateway}    Unified webhook handler with signature verification
POST /api/v1/payment/coupons/validate     Validate coupon code against user and cart
POST /api/v1/payment/coupons/apply        Calculate discount and return final amount
```

## Requirements

PHP 8.2+, Laravel 12 or 13, Livewire 4, and Flux 2.

## License

MIT.
