<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Kreetancraft\PaymentGateway\Http\Controllers\Api\CheckoutController;
use Kreetancraft\PaymentGateway\Http\Controllers\CouponController;
use Kreetancraft\PaymentGateway\Http\Controllers\PaymentController;
use Kreetancraft\PaymentGateway\Http\Controllers\WebhookController;

Route::post('checkout', [CheckoutController::class, 'apiCheckout'])
    ->name('api.payment.checkout');

Route::post('verify', [PaymentController::class, 'verify'])
    ->name('api.payment.verify');

Route::post('refund', [PaymentController::class, 'refund'])
    ->name('api.payment.refund');

Route::get('gateways', [PaymentController::class, 'gateways'])
    ->name('api.payment.gateways');

Route::post('webhook/{gateway}', WebhookController::class)
    ->name('payment.webhook');

// Coupon API routes
Route::prefix('coupons')->name('coupons.')->group(function (): void {
    Route::get('/', [CouponController::class, 'listCoupons'])
        ->name('list');
    Route::post('validate', [CouponController::class, 'validateCoupon'])
        ->name('validate');
    Route::post('apply', [CouponController::class, 'applyCoupon'])
        ->name('apply');
});
