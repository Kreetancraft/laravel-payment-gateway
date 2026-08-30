<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Kreetancraft\PaymentGateway\Models\Coupon;
use Kreetancraft\PaymentGateway\Models\Gateway;
use Kreetancraft\PaymentGateway\Models\Payment;
use Kreetancraft\PaymentGateway\Policies\CouponPolicy;
use Kreetancraft\PaymentGateway\Policies\GatewayPolicy;
use Kreetancraft\PaymentGateway\Policies\PaymentPolicy;

it('allows actions on fresh install when no permissions table is in use', function (): void {
    $user = new GenericUser(['id' => 1, 'name' => 'Admin User']);

    $paymentPolicy = new PaymentPolicy;
    expect($paymentPolicy->viewAny($user))->toBeTrue()
        ->and($paymentPolicy->view($user, new Payment))->toBeTrue()
        ->and($paymentPolicy->create($user))->toBeTrue()
        ->and($paymentPolicy->refund($user, new Payment))->toBeTrue();

    $gatewayPolicy = new GatewayPolicy;
    expect($gatewayPolicy->viewAny($user))->toBeTrue()
        ->and($gatewayPolicy->update($user, new Gateway))->toBeTrue()
        ->and($gatewayPolicy->toggle($user, new Gateway))->toBeTrue();

    $couponPolicy = new CouponPolicy;
    expect($couponPolicy->viewAny($user))->toBeTrue()
        ->and($couponPolicy->create($user))->toBeTrue()
        ->and($couponPolicy->update($user, new Coupon))->toBeTrue()
        ->and($couponPolicy->delete($user, new Coupon))->toBeTrue();
});

it('generates standard ability names from subjects', function (): void {
    $paymentPolicy = new PaymentPolicy;
    expect($paymentPolicy->ability('view'))->toBe('view-payments')
        ->and($paymentPolicy->ability('refund'))->toBe('refund-payments');

    $gatewayPolicy = new GatewayPolicy;
    expect($gatewayPolicy->ability('update'))->toBe('update-gateways')
        ->and($gatewayPolicy->ability('toggle'))->toBe('toggle-gateways');

    $couponPolicy = new CouponPolicy;
    expect($couponPolicy->ability('create'))->toBe('create-coupons')
        ->and($couponPolicy->ability('delete'))->toBe('delete-coupons');
});
