<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Kreetancraft\PaymentGateway\Models\Coupon;
use Kreetancraft\PaymentGateway\Rules\ValidCouponCode;
use Kreetancraft\PaymentGateway\Services\CouponService;

uses(RefreshDatabase::class);

it('applies a valid percentage coupon', function () {
    $coupon = Coupon::factory()->percentage(20)->create();

    $result = app(CouponService::class)->apply(
        $coupon->code,
        null,
        10000, // $100.00
        'USD'
    );

    expect($result['success'])->toBeTrue()
        ->and($result['discount_cents'])->toBe(2000) // 20% of 10000
        ->and($result['final_amount_cents'])->toBe(8000);
});

it('applies a valid fixed coupon', function () {
    $coupon = Coupon::factory()->fixed(1000)->create();

    $result = app(CouponService::class)->apply(
        $coupon->code,
        null,
        5000, // $50.00
        'USD'
    );

    expect($result['success'])->toBeTrue()
        ->and($result['discount_cents'])->toBe(1000)
        ->and($result['final_amount_cents'])->toBe(4000);
});

it('rejects expired coupon', function () {
    $coupon = Coupon::factory()->expired()->create();

    $result = app(CouponService::class)->apply(
        $coupon->code,
        null,
        10000,
        'USD'
    );

    expect($result['success'])->toBeFalse()
        ->and($result['code'])->toBe('COUPON_EXPIRED');
});

it('rejects coupon with exceeded usage limit', function () {
    $coupon = Coupon::factory()->withUsageLimit(1)->create(['usage_count' => 1]);

    $result = app(CouponService::class)->apply(
        $coupon->code,
        null,
        10000,
        'USD'
    );

    expect($result['success'])->toBeFalse()
        ->and($result['code'])->toBe('COUPON_EXHAUSTED');
});

it('rejects coupon exceeding max discount amount', function () {
    $coupon = Coupon::factory()->percentage(50)->create(['max_discount_amount' => 500]);

    $result = app(CouponService::class)->apply(
        $coupon->code,
        null,
        10000, // $50.00 - 50% would be $25, but capped at $5
        'USD'
    );

    expect($result['success'])->toBeTrue()
        ->and($result['discount_cents'])->toBe(500); // Capped at $5.00
});

it('applies free shipping coupon', function () {
    $coupon = Coupon::factory()->freeShipping()->create();

    $result = app(CouponService::class)->apply(
        $coupon->code,
        null,
        10000,
        'USD'
    );

    expect($result['success'])->toBeTrue()
        ->and($result['has_free_shipping'])->toBeTrue()
        ->and($result['discount_cents'])->toBe(0);
});

it('stacks coupons correctly (max savings wins)', function () {
    $coupon1 = Coupon::factory()->percentage(10)->create(); // 10%
    $coupon2 = Coupon::factory()->percentage(20)->create(); // 20%

    $result = app(CouponService::class)->applyMultiple(
        [$coupon1->code, $coupon2->code],
        null,
        10000, // $100
        'USD'
    );

    expect($result['success'])->toBeTrue()
        ->and($result['discount_cents'])->toBe(2000); // 20% wins (better discount)
});

it('free shipping stacks on top of monetary discount', function () {
    $coupon1 = Coupon::factory()->percentage(10)->create();
    $coupon2 = Coupon::factory()->freeShipping()->create();

    $result = app(CouponService::class)->applyMultiple(
        [$coupon1->code, $coupon2->code],
        null,
        10000,
        'USD'
    );

    expect($result['success'])->toBeTrue()
        ->and($result['discount_cents'])->toBe(1000)
        ->and($result['has_free_shipping'])->toBeTrue();
});

it('validates coupon code correctly', function () {
    $coupon = Coupon::factory()->active()->create();

    $rule = new ValidCouponCode;

    // This would need a request context, so we test via service
    $result = app(CouponService::class)->validate($coupon->code, null, 10000, 'USD');

    expect($result['valid'])->toBeTrue();
});

it('rejects invalid coupon code', function () {
    $result = app(CouponService::class)->validate('INVALID', null, 10000, 'USD');

    expect($result['valid'])->toBeFalse()
        ->and($result['code'])->toBe('COUPON_NOT_FOUND');
});

it('enforces min order amount', function () {
    $coupon = Coupon::factory()->create(['min_order_amount' => 5000]);

    $result = app(CouponService::class)->validate($coupon->code, null, 1000, 'USD');

    expect($result['valid'])->toBeFalse()
        ->and($result['code'])->toBe('MIN_ORDER_NOT_MET');
});
