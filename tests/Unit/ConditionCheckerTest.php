<?php

use Kreetancraft\PaymentGateway\Models\Coupon;
use Kreetancraft\PaymentGateway\Support\ConditionChecker;

it('checks minimum order amount correctly', function (): void {
    $checker = new ConditionChecker;
    $coupon = new Coupon([
        'conditions' => ['min_order_amount' => 5000],
    ]);

    expect($checker->check($coupon, ['amount_cents' => 6000]))->toBeTrue()
        ->and($checker->check($coupon, ['amount_cents' => 3000]))->toBeFalse();
});

it('checks currency restrictions correctly', function (): void {
    $checker = new ConditionChecker;
    $coupon = new Coupon([
        'conditions' => ['currencies' => ['USD', 'EUR']],
    ]);

    expect($checker->check($coupon, ['currency' => 'usd']))->toBeTrue()
        ->and($checker->check($coupon, ['currency' => 'EUR']))->toBeTrue()
        ->and($checker->check($coupon, ['currency' => 'NPR']))->toBeFalse();
});

it('checks user whitelist correctly', function (): void {
    $checker = new ConditionChecker;
    $coupon = new Coupon([
        'conditions' => ['user_ids' => [1, 2, 3]],
    ]);

    expect($checker->check($coupon, ['user_id' => 2]))->toBeTrue()
        ->and($checker->check($coupon, ['user_id' => 99]))->toBeFalse();
});

it('evaluates custom closure conditions', function (): void {
    $checker = new ConditionChecker;
    $conditions = [
        'custom' => [
            fn (array $context): bool => ($context['vip_member'] ?? false) === true,
        ],
    ];

    expect($checker->check($conditions, ['vip_member' => true]))->toBeTrue()
        ->and($checker->check($conditions, ['vip_member' => false]))->toBeFalse();
});
