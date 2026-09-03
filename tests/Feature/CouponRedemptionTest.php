<?php

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kreetancraft\PaymentGateway\Models\Coupon;
use Kreetancraft\PaymentGateway\Models\CouponUsage;

uses(RefreshDatabase::class);

/**
 * One coupon, one order, one redemption.
 *
 * `recordUsage()` was a bare `create()`, so nothing stopped the same coupon
 * being recorded against the same order twice — a double-clicked apply, a
 * retried job, a redelivered webhook. Every duplicate counted again toward the
 * coupon's usage cap and its reported discount, so a coupon limited to 100
 * redemptions could be exhausted by 50 customers.
 */
function aCoupon(): Coupon
{
    return Coupon::create([
        'code' => 'SUMMER20',
        'name' => 'Summer 20%',
        'type' => 'percentage',
        'value' => 20,
        'is_active' => true,
    ]);
}

it('records a redemption once for one order', function (): void {
    $coupon = aCoupon();

    CouponUsage::recordUsage($coupon->id, 1, 'invoice', 7, 5000, 'USD');
    CouponUsage::recordUsage($coupon->id, 1, 'invoice', 7, 5000, 'USD');

    expect(CouponUsage::where('coupon_id', $coupon->id)->count())->toBe(1);
});

it('hands back the redemption it already had', function (): void {
    $coupon = aCoupon();

    $first = CouponUsage::recordUsage($coupon->id, 1, 'invoice', 7, 5000, 'USD');
    $second = CouponUsage::recordUsage($coupon->id, 1, 'invoice', 7, 5000, 'USD');

    expect($second->id)->toBe($first->id);
});

it('does not add the discount twice', function (): void {
    // The number that matters: a repeat must not inflate what the coupon has
    // given away.
    $coupon = aCoupon();

    CouponUsage::recordUsage($coupon->id, 1, 'invoice', 7, 5000, 'USD');
    CouponUsage::recordUsage($coupon->id, 1, 'invoice', 7, 5000, 'USD');

    expect((int) CouponUsage::where('coupon_id', $coupon->id)->sum('amount_discounted_cents'))->toBe(5000);
});

it('still records the same coupon against a different order', function (): void {
    // Two customers using one code is two redemptions, not a duplicate.
    $coupon = aCoupon();

    CouponUsage::recordUsage($coupon->id, 1, 'invoice', 7, 5000, 'USD');
    CouponUsage::recordUsage($coupon->id, 2, 'invoice', 8, 5000, 'USD');

    expect(CouponUsage::where('coupon_id', $coupon->id)->count())->toBe(2);
});

it('still records a different coupon against the same order', function (): void {
    $first = aCoupon();
    $second = Coupon::create([
        'code' => 'FREESHIP', 'name' => 'Free shipping', 'type' => 'fixed',
        'value' => 500, 'is_active' => true,
    ]);

    CouponUsage::recordUsage($first->id, 1, 'invoice', 7, 5000, 'USD');
    CouponUsage::recordUsage($second->id, 1, 'invoice', 7, 500, 'USD');

    expect(CouponUsage::where('order_id', 7)->count())->toBe(2);
});

it('refuses a duplicate at the database too', function (): void {
    // The application guard can be bypassed by anything writing directly. The
    // index is what makes "twice" impossible rather than merely avoided.
    $coupon = aCoupon();
    CouponUsage::recordUsage($coupon->id, 1, 'invoice', 7, 5000, 'USD');

    expect(fn () => CouponUsage::query()->insert([
        'coupon_id' => $coupon->id,
        'user_id' => 1,
        'order_type' => 'invoice',
        'order_id' => 7,
        'usage_count' => 1,
        'amount_discounted_cents' => 5000,
        'currency' => 'USD',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(UniqueConstraintViolationException::class);
});
