<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Kreetancraft\PaymentGateway\Actions\RefundPaymentAction;
use Kreetancraft\PaymentGateway\Models\Payment;

uses(RefreshDatabase::class);

/**
 * What this package exposes without a login, and what it does not.
 *
 * The monolith's Payments module publishes exactly one API route — a gateway
 * callback on `throttle:30,1` — and keeps refunding on the invoice screen,
 * where it is authorized. This package had drifted a long way from that: a
 * refund endpoint anyone could call, an inquiry endpoint into the bank on every
 * request, a listing of every active discount code, and no rate limit anywhere.
 */
function routeNames(): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->map(fn ($route): ?string => $route->getName())
        ->filter()
        ->values()
        ->all();
}

function routeFor(string $name): ?\Illuminate\Routing\Route
{
    return collect(Route::getRoutes()->getRoutes())
        ->first(fn ($route): bool => $route->getName() === $name);
}

it('publishes no refund endpoint', function (): void {
    // Refunding moves money out. It belongs on the transactions screen, behind
    // an authorization check, the way the monolith keeps it on the invoice.
    expect(routeNames())->not->toContain('api.payment.refund');
});

it('rate limits the gateway webhook', function (): void {
    $route = routeFor('payment.webhook');

    expect($route)->not->toBeNull()
        ->and($route->gatherMiddleware())->toContain('throttle:30,1');
});

it('rate limits the endpoints a buyer can reach', function (): void {
    foreach (['api.payment.checkout', 'api.payment.gateways'] as $name) {
        $route = routeFor($name);

        expect($route)->not->toBeNull($name)
            ->and(collect($route->gatherMiddleware())->contains(fn ($m): bool => str_starts_with((string) $m, 'throttle:')))
            ->toBeTrue($name);
    }
});

it('keeps gateway inquiry and the coupon listing behind auth', function (): void {
    // verify() calls out to the bank, so leaving it open lets anyone probe
    // order numbers on your rate limit. The coupon listing returns every active
    // code and its value.
    foreach (['api.payment.verify', 'coupons.list'] as $name) {
        $route = routeFor($name);

        // toContain takes values, not a message — passing $name as a second
        // argument asserted the middleware list contained the route name too.
        expect($route)->not->toBeNull($name)
            ->and(in_array('auth', $route->gatherMiddleware(), true))->toBeTrue($name);
    }
});

it('leaves redeeming a code a buyer can do', function (): void {
    // Both take a code the caller already holds, so they stay public.
    foreach (['coupons.validate', 'coupons.apply'] as $name) {
        expect(routeNames())->toContain($name);
    }
});

it('refunds a payment from the transactions screen', function (): void {
    // The only authorized refund path in the package called
    // RefundPaymentAction::run($payment) — a model into a string parameter,
    // with no amount — so it threw ArgumentCountError every time. Nothing
    // covered it, while the unauthenticated route called the action correctly.
    $payment = Payment::factory()->create([
        'gateway_reference' => 'pi_screen_1',
        'amount_cents' => 20000,
        'refunded_amount_cents' => 0,
    ]);

    $result = RefundPaymentAction::forPayment($payment);

    // It reaches the gateway rather than dying on its own signature; whether
    // the mock gateway settles is another test's business.
    expect($result)->toBeInstanceOf(\Kreetancraft\PaymentGateway\Data\RefundResult::class)
        ->and($result->errorCode)->not->toBe('transaction_missing')
        ->and($result->errorCode)->not->toBe('invalid_amount');
});

it('defaults a screen refund to the outstanding balance', function (): void {
    $payment = Payment::factory()->create([
        'gateway_reference' => 'pi_screen_2',
        'amount_cents' => 20000,
        'refunded_amount_cents' => 5000,
    ]);

    $result = RefundPaymentAction::forPayment($payment);

    expect($result->amount)->toBe(150.0);
});
