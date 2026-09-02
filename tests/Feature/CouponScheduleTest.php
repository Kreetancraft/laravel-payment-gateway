<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Kreetancraft\PaymentGateway\Livewire\CreateCoupon;
use Kreetancraft\PaymentGateway\Livewire\EditCoupon;
use Kreetancraft\PaymentGateway\Models\Coupon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * The optional schedule, left alone.
 *
 * Both date fields are marked optional on the form, but Livewire binds an empty
 * date input as `''` rather than null, and the model casts those columns to
 * datetime — where `''` reads as the current time. So a coupon created without
 * touching the schedule was stamped `expires_at = now()` and was expired before
 * the admin got back to the list. It showed as Inactive and no buyer could ever
 * redeem it.
 */
it('leaves the schedule empty when the admin does', function (): void {
    Livewire::test(CreateCoupon::class)
        ->set('code', 'NOSCHEDULE')
        ->set('name', 'No schedule')
        ->set('type', 'percentage')
        ->set('value', 20)
        ->set('startsAt', '')
        ->set('expiresAt', '')
        ->call('save');

    $coupon = Coupon::where('code', 'NOSCHEDULE')->first();

    expect($coupon)->not->toBeNull()
        ->and($coupon->starts_at)->toBeNull()
        ->and($coupon->expires_at)->toBeNull();
});

it('creates a coupon a buyer can actually redeem', function (): void {
    // The symptom, stated the way it was noticed: created active, listed
    // inactive.
    Livewire::test(CreateCoupon::class)
        ->set('code', 'USABLE')
        ->set('name', 'Usable')
        ->set('type', 'percentage')
        ->set('value', 20)
        ->call('save');

    $coupon = Coupon::where('code', 'USABLE')->first();

    expect($coupon->isValid())->toBeTrue();
});

it('still honours a schedule that was given', function (): void {
    Livewire::test(CreateCoupon::class)
        ->set('code', 'SCHEDULED')
        ->set('name', 'Scheduled')
        ->set('type', 'percentage')
        ->set('value', 10)
        ->set('startsAt', '2026-01-01 00:00')
        ->set('expiresAt', '2026-12-31 23:59')
        ->call('save');

    $coupon = Coupon::where('code', 'SCHEDULED')->first();

    expect($coupon->starts_at)->not->toBeNull()
        ->and($coupon->expires_at->format('Y-m-d'))->toBe('2026-12-31');
});

it('does not invent a schedule when one is cleared', function (): void {
    $coupon = Coupon::create([
        'code' => 'CLEARME',
        'name' => 'Clear me',
        'type' => 'percentage',
        'value' => 10,
        'is_active' => true,
        'starts_at' => now()->subDay(),
        'expires_at' => now()->addDay(),
    ]);

    Livewire::test(EditCoupon::class, ['id' => $coupon->id])
        ->set('startsAt', '')
        ->set('expiresAt', '')
        ->call('save');

    expect($coupon->fresh()->starts_at)->toBeNull()
        ->and($coupon->fresh()->expires_at)->toBeNull();
});
