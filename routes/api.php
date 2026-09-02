<?php

use Illuminate\Support\Facades\Route;
use Kreetancraft\PaymentGateway\Http\Controllers\Api\CheckoutController;
use Kreetancraft\PaymentGateway\Http\Controllers\CouponController;
use Kreetancraft\PaymentGateway\Http\Controllers\PaymentController;
use Kreetancraft\PaymentGateway\Http\Controllers\WebhookController;

/*
|--------------------------------------------------------------------------
| Payment API Routes
|--------------------------------------------------------------------------
|
| Two audiences, and they are not the same. A buyer who has not signed in has
| to be able to start a payment and redeem a code they already hold. Everything
| that reads back or moves money is staff work and sits behind auth.
|
| There is deliberately no refund endpoint. Refunding happens on the
| transactions screen, authorized against the payment
| (ManageTransactions::refund) — the same choice the monolith makes by keeping
| refunds on the invoice rather than on a route.
|
| Every group carries a rate limit. Gateway traffic is not client traffic, so
| the webhook keeps its own plain inline limit; the rest are limited because
| each one either costs an outbound call to the bank or probes for a valid
| reference.
|
*/

$protected = config('payment-gateway.routes.protected_middleware', ['auth']);

// --- Public: a buyer who is not signed in ------------------------------------

Route::middleware('throttle:20,1')->group(function (): void {
    Route::post('checkout', [CheckoutController::class, 'apiCheckout'])
        ->name('api.payment.checkout');

    Route::prefix('coupons')->name('coupons.')->group(function (): void {
        // Both take a code the caller already holds. Neither hands one out.
        Route::post('validate', [CouponController::class, 'validateCoupon'])->name('validate');
        Route::post('apply', [CouponController::class, 'applyCoupon'])->name('apply');
    });
});

Route::middleware('throttle:60,1')
    ->get('gateways', [PaymentController::class, 'gateways'])
    ->name('api.payment.gateways');

// --- Gateway traffic ----------------------------------------------------------

// Not client traffic, so it stays on a plain inline limit. Unauthenticated by
// necessity — the bank calls it — but safe to be: the handler reads only the
// order number from the body and then asks PACO directly what happened, so a
// forged payload cannot assert that a payment succeeded.
Route::middleware('throttle:30,1')
    ->post('webhook/{gateway}', WebhookController::class)
    ->name('payment.webhook');

// --- Staff ---------------------------------------------------------------------

Route::middleware($protected)->group(function (): void {
    // An inquiry against the gateway. Left open, it lets anyone probe order
    // numbers and spend the bank's rate limit doing it.
    Route::post('verify', [PaymentController::class, 'verify'])
        ->name('api.payment.verify');

    // Every active code, values included. This is a staff listing; publishing it
    // hands out the discounts it exists to control.
    Route::get('coupons', [CouponController::class, 'listCoupons'])
        ->name('coupons.list');
});
