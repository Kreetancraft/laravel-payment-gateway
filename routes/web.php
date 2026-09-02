<?php

use Illuminate\Support\Facades\Route;
use Kreetancraft\PaymentGateway\Http\Controllers\CouponController;
use Kreetancraft\PaymentGateway\Http\Controllers\PaymentController;
use Kreetancraft\PaymentGateway\Livewire\Checkout;
use Kreetancraft\PaymentGateway\Livewire\CreateCoupon;
use Kreetancraft\PaymentGateway\Livewire\EditCoupon;
use Kreetancraft\PaymentGateway\Livewire\EditGateway;
use Kreetancraft\PaymentGateway\Livewire\ManageCoupons;
use Kreetancraft\PaymentGateway\Livewire\ManageGateways;
use Kreetancraft\PaymentGateway\Livewire\ManageTransactions;
use Kreetancraft\PaymentGateway\Livewire\ShowCoupon;

$names = (array) config('payment-gateway.routes.names', []);

// Admin Management Screens (Livewire + Flux UI)
if (config('payment-gateway.routes.register_admin', true)) {
    Route::get('gateways', ManageGateways::class)
        ->name($names['gateways'] ?? 'admin.payment.gateways');

    Route::get('gateways/{code}/edit', EditGateway::class)
        ->name($names['gateways_edit'] ?? 'admin.payment.gateways.edit');

    Route::get('coupons', ManageCoupons::class)
        ->name($names['coupons'] ?? 'admin.payment.coupons');

    Route::get('coupons/create', CreateCoupon::class)
        ->name($names['coupons_create'] ?? 'admin.payment.coupons.create');

    Route::get('coupons/{id}/edit', EditCoupon::class)
        ->name($names['coupons_edit'] ?? 'admin.payment.coupons.edit');

    Route::get('coupons/{id}', ShowCoupon::class)
        ->name($names['coupons_show'] ?? 'admin.payment.coupons.show');

    Route::get('transactions', ManageTransactions::class)
        ->name($names['transactions'] ?? 'admin.payment.transactions');
}

// Hosted Checkout & Return Flow (UI Views)
if (config('payment-gateway.routes.register_ui', true)) {
    Route::get('checkout/{gateway?}', Checkout::class)
        ->name($names['checkout'] ?? 'payment.checkout');

    Route::get('choose', [PaymentController::class, 'choose'])
        ->name('payment.choose');

    Route::get('success', [PaymentController::class, 'success'])
        ->name($names['success'] ?? 'payment.success');

    Route::get('cancel', [PaymentController::class, 'cancel'])
        ->name($names['cancel'] ?? 'payment.cancel');

    Route::get('failed', [PaymentController::class, 'failed'])
        ->name($names['failed'] ?? 'payment.failed');
}

// Public Coupon API endpoints (if needed over web session)
Route::prefix('api-coupons')->name('coupons.')->group(function (): void {
    // A code the caller already holds; redeeming is buyer work.
    Route::middleware('throttle:20,1')->group(function (): void {
        Route::post('validate', [CouponController::class, 'validateCoupon'])
            ->name('validate');
        Route::post('apply', [CouponController::class, 'applyCoupon'])
            ->name('apply');
    });

    // The full list of active codes and their values is staff work. Served
    // over the web session as well as the API, so it needs gating in both.
    Route::middleware(config('payment-gateway.routes.protected_middleware', ['auth']))
        ->get('/', [CouponController::class, 'listCoupons'])
        ->name('list');
});
