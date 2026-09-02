<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Kreetancraft\PaymentGateway\Models\Coupon;
use Kreetancraft\PaymentGateway\Support\CouponStacker;

uses(RefreshDatabase::class);

it('returns zero discount when no valid coupon codes provided', function (): void {
    $stacker = new CouponStacker;
    $result = $stacker->apply(['NON_EXISTENT'], 10000, 'USD');

    expect($result['discount_cents'])->toBe(0)
        ->and($result['final_amount_cents'])->toBe(10000)
        ->and($result['applied_coupons'])->toBeEmpty()
        ->and($result['has_free_shipping'])->toBeFalse();
});

it('stacks free shipping on top of monetary discount', function (): void {
    $fixed = Coupon::factory()->fixed(1500)->create(['code' => 'SAVE15']);
    $freeShipping = Coupon::factory()->freeShipping()->create(['code' => 'FREESHIP']);

    $stacker = new CouponStacker;
    $result = $stacker->apply(['SAVE15', 'FREESHIP'], 10000, 'USD');

    expect($result['discount_cents'])->toBe(1500)
        ->and($result['final_amount_cents'])->toBe(8500)
        ->and($result['has_free_shipping'])->toBeTrue();
});

it('finds best savings combination from multiple coupons', function (): void {
    $coupon10 = Coupon::factory()->percentage(10)->create(['code' => 'PERC10']); // $10 off $100
    $coupon25 = Coupon::factory()->percentage(25)->create(['code' => 'PERC25']); // $25 off $100

    $stacker = new CouponStacker;
    $result = $stacker->apply(['PERC10', 'PERC25'], 10000, 'USD');

    expect($result['discount_cents'])->toBe(2500)
        ->and($result['final_amount_cents'])->toBe(7500);
});
