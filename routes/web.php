<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Kreetancraft\PaymentGateway\Http\Controllers\CouponController;
use Kreetancraft\PaymentGateway\Http\Controllers\PaymentController;
use Kreetancraft\PaymentGateway\Livewire\Checkout;
use Kreetancraft\PaymentGateway\Livewire\ManageCoupons;
use Kreetancraft\PaymentGateway\Livewire\ManageGateways;

$prefix = (string) config('payment-gateway.routes.prefix', 'payment');
$middleware = (array) config('payment-gateway.routes.middleware', ['web']);
$names = (array) config('payment-gateway.routes.names', []);

Route::prefix($prefix)
    ->middleware($middleware)
    ->group(function () use ($names): void {
        // Admin Management Screens (Livewire + Flux UI)
        Route::get('gateways', ManageGateways::class)
            ->name($names['gateways'] ?? 'payment.gateways');

        Route::get('manage-coupons', ManageCoupons::class)
            ->name($names['coupons'] ?? 'payment.coupons');

        // Hosted Checkout Flow
        Route::get('checkout/{gateway?}', Checkout::class)
            ->name($names['checkout'] ?? 'payment.checkout');

        Route::get('choose', [PaymentController::class, 'choose'])
            ->name('payment.choose');

        Route::get('success', [PaymentController::class, 'success'])
            ->name($names['success'] ?? 'payment.success');

        Route::get('cancel', [PaymentController::class, 'cancel'])
            ->name($names['cancel'] ?? 'payment.cancel');

        // Coupon actions
        Route::prefix('coupons')->name('coupons.')->group(function (): void {
            Route::get('/', [CouponController::class, 'listCoupons'])
                ->name('list');
            Route::post('validate', [CouponController::class, 'validateCoupon'])
                ->name('validate');
            Route::post('apply', [CouponController::class, 'applyCoupon'])
                ->name('apply');
        });
    });
