<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Kreetancraft\PaymentGateway\Http\Controllers\PaymentController;
use Kreetancraft\PaymentGateway\Http\Controllers\WebhookController;
use Kreetancraft\PaymentGateway\Livewire\Checkout;
use Kreetancraft\PaymentGateway\Livewire\ManageGateways;
use Kreetancraft\PaymentGateway\Livewire\ManageCoupons;
use Kreetancraft\PaymentGateway\Http\Controllers\CouponController;

$prefix = config('payment-gateway.routes.prefix', 'payment');
$middleware = config('payment-gateway.routes.middleware', ['web']);
$names = config('payment-gateway.routes.names', []);

Route::prefix($prefix)
    ->middleware($middleware)
    ->group(function () use ($names): void {
        Route::get('checkout/{gateway?}', \Kreetancraft\PaymentGateway\Livewire\Checkout::class)
            ->name($names['checkout'] ?? 'payment.checkout');

        Route::get('choose', [PaymentController::class, 'choose'])
            ->name('payment.choose');

        Route::get('success', [PaymentController::class, 'success'])
            ->name($names['success'] ?? 'payment.success');

        Route::get('cancel', [PaymentController::class, 'cancel'])
            ->name($names['cancel'] ?? 'payment.cancel');

        // Coupon routes
        Route::prefix('coupons')->name('coupons.')->group(function () use ($names): void {
            Route::get('/', [CouponController::class, 'listCoupons'])
                ->name('list');
            Route::post('validate', [CouponController::class, 'validateCoupon'])
                ->name('validate');
            Route::post('apply', [CouponController::class, 'applyCoupon'])
                ->name('apply');
        });
    });