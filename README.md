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

## What can be paid for

A checkout names a thing, never a price. Implement `Payable` on whatever you
sell and register it under an alias:

```php
// config/payment-gateway.php
'payables' => [
    'invoice' => \App\Models\Invoice::class,
],
```

```php
class Invoice extends Model implements Payable
{
    public function paymentAmountCents(): int { return $this->balance_due_cents; }
    public function paymentCurrency(): string { return $this->currency; }
    public function paymentReference(): string { return $this->number; }
    public function paymentDescription(): ?string { return "Invoice {$this->number}"; }
}
```

```
POST /api/v1/payment/checkout   { "payable_type": "invoice", "payable_id": 42 }
```

The amount and currency are read off the model. An `amount_cents` in the request
is ignored — earlier versions accepted it on a public route, which meant the
buyer chose the price. An alias not in the allowlist is refused, so a caller
cannot point checkout at an arbitrary model.

Return what is still **outstanding**, not the original total: that is what gets
charged, and a partly-paid payable must not be charged twice over.

## Public & Private API

```
POST /api/v1/payment/checkout             Initiate a charge for a payable
GET  /api/v1/payment/gateways             Enabled gateways and their currencies
POST /api/v1/payment/coupons/validate     Validate a code the caller already holds
POST /api/v1/payment/coupons/apply        Calculate a discount
POST /api/v1/payment/webhook/{gateway}    Gateway callback
```

Behind `auth`:

```
POST /api/v1/payment/verify               Ask the gateway about a transaction
GET  /api/v1/payment/coupons              Every active code and its value
```

**There is no refund endpoint.** Refunding moves money out; it happens on the
transactions screen, authorized against the payment. Everything above is rate
limited — the webhook because each request costs an outbound call to the bank,
the rest because they either probe for valid references or cost money.

## Operations

```bash
php artisan payment-gateway:status
```

Names every missing credential and exits non-zero, so a deploy can gate on it.
Worth running before go-live: every way a gateway is misconfigured looks the
same from outside, and the buyer is the one who finds out.

```bash
php artisan payment-gateway:reconcile
```

Re-asks the gateway about payments still pending. Schedule it. A dropped
callback otherwise means money taken and an order that never completes, with
nothing that knows to look. Safe to run often — verification is idempotent and
never downgrades a settled payment.

## Requirements

PHP 8.2+ with `ext-openssl` and `ext-gmp`, Laravel 12 or 13, Livewire 4, Flux 2.

`ext-gmp` is not optional in practice — the vendor's own integration notes call for it to
avoid RSA timeouts on the JOSE handshake.

## License

MIT.
