<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Services;

use Illuminate\Support\Facades\DB;
use Kreetancraft\PaymentGateway\Contracts\GatewayResolver;
use Kreetancraft\PaymentGateway\Models\Coupon;
use Kreetancraft\PaymentGateway\Models\CouponUsage;
use Kreetancraft\PaymentGateway\Support\CouponStacker;
use Lorisleiva\Actions\Concerns\AsAction;

class CouponService
{
    use AsAction;

    public function __construct(
        private readonly GatewayResolver $resolver
    ) {}

    /**
     * Apply a coupon code to an amount
     * Main entry point - handles single and multiple coupons
     */
    public function handle(array $data): array
    {
        $code = $data['code'];
        $userId = $data['user_id'] ?? null;
        $amountCents = $data['amount_cents'];
        $currency = $data['currency'] ?? 'USD';
        $context = $data['context'] ?? [];

        return $this->apply($code, $userId, $amountCents, $currency, $context);
    }

    /**
     * Apply a single coupon code to an amount
     * Returns discount info and final amount
     */
    public function apply(string $code, ?int $userId, int $amountCents, string $currency, array $context = []): array
    {
        $coupon = Coupon::where('code', $code)->firstOrFail();

        $validation = $this->validate($code, $userId, $amountCents, $currency);

        if (! $validation['valid']) {
            return [
                'success' => false,
                'message' => $validation['message'],
                'code' => $validation['code'] ?? 'INVALID_COUPON',
            ];
        }

        $isFreeShipping = (bool) $coupon->is_free_shipping || $coupon->type === 'free_shipping';
        $discountCents = $coupon->calculateDiscount($amountCents);

        if ($discountCents <= 0 && ! $isFreeShipping) {
            return [
                'success' => false,
                'message' => 'Coupon does not apply to this amount.',
                'code' => 'NO_DISCOUNT',
            ];
        }

        $finalAmountCents = max(0, $amountCents - $discountCents);

        return [
            'success' => true,
            'discount_cents' => $discountCents,
            'final_amount_cents' => $finalAmountCents,
            'has_free_shipping' => $isFreeShipping,
            'coupon' => [
                'code' => $coupon->code,
                'label' => $coupon->name ?? $coupon->label ?? $coupon->code,
                'type' => $coupon->type,
                'value' => $coupon->value,
            ],
            'amount_cents' => $amountCents,
            'currency' => $currency,
        ];
    }

    /**
     * Validate a coupon code against user, amount, currency
     * Returns array with 'valid' boolean and error details if invalid
     */
    public function validate(string $code, ?int $userId, int $amountCents, string $currency): array
    {
        $coupon = Coupon::where('code', $code)->first();

        if (! $coupon) {
            return [
                'valid' => false,
                'message' => 'Coupon code not found.',
                'code' => 'COUPON_NOT_FOUND',
            ];
        }

        if ($coupon->expires_at && $coupon->expires_at->isPast()) {
            return [
                'valid' => false,
                'message' => 'This coupon has expired.',
                'code' => 'COUPON_EXPIRED',
            ];
        }

        if (! $coupon->is_active) {
            return [
                'valid' => false,
                'message' => 'This coupon is not active.',
                'code' => 'COUPON_INACTIVE',
            ];
        }

        if ($coupon->starts_at && $coupon->starts_at->isFuture()) {
            return [
                'valid' => false,
                'message' => 'This coupon is not yet valid.',
                'code' => 'COUPON_NOT_STARTED',
            ];
        }

        if ($coupon->usage_limit && $coupon->usage_count >= $coupon->usage_limit) {
            return [
                'valid' => false,
                'message' => 'This coupon has reached its usage limit.',
                'code' => 'COUPON_EXHAUSTED',
            ];
        }

        if ($userId !== null && $userId > 0) {
            $userUsage = CouponUsage::where('coupon_id', $coupon->id)
                ->where('user_id', $userId)
                ->sum('usage_count');

            if ($coupon->usage_limit_per_user && $userUsage >= $coupon->usage_limit_per_user) {
                return [
                    'valid' => false,
                    'message' => 'You have reached the usage limit for this coupon.',
                    'code' => 'USER_LIMIT_EXCEEDED',
                ];
            }

            if ($coupon->user_ids && ! in_array($userId, $coupon->user_ids)) {
                return [
                    'valid' => false,
                    'message' => 'This coupon is not available for your account.',
                    'code' => 'COUPON_NOT_FOR_USER',
                ];
            }
        }

        if ($amountCents < $coupon->min_order_amount) {
            return [
                'valid' => false,
                'message' => 'Minimum order amount is '.number_format(($coupon->min_order_amount ?? 0) / 100, 2).'.',
                'code' => 'MIN_ORDER_NOT_MET',
            ];
        }

        if (! $coupon->supportsCurrency($currency)) {
            return [
                'valid' => false,
                'message' => "This coupon is not valid for {$currency}.",
                'code' => 'CURRENCY_NOT_SUPPORTED',
            ];
        }

        return ['valid' => true];
    }

    public function redeem(Coupon $coupon, int $userId, string $orderType, int $orderId, int $amountDiscountedCents, string $currency, array $metadata = []): CouponUsage
    {
        return DB::transaction(function () use ($coupon, $userId, $orderType, $orderId, $amountDiscountedCents, $currency, $metadata) {
            $coupon->increment('usage_count');

            $usage = CouponUsage::recordUsage(
                couponId: $coupon->id,
                userId: $userId,
                orderType: $orderType,
                orderId: $orderId,
                amountDiscountedCents: $amountDiscountedCents,
                currency: $currency,
                metadata: $metadata
            );

            return $usage;
        });
    }

    public function applyMultiple(array $codes, ?int $userId, int $amountCents, string $currency, array $context = []): array
    {
        $stacker = new CouponStacker;
        $result = $stacker->apply($codes, $amountCents, $currency);

        if ($result['discount_cents'] === 0 && ! $result['has_free_shipping']) {
            return [
                'success' => false,
                'message' => 'No valid coupons provided.',
                'code' => 'NO_VALID_COUPONS',
            ];
        }

        return [
            'success' => true,
            'discount_cents' => $result['discount_cents'],
            'final_amount_cents' => $result['final_amount_cents'],
            'applied_coupons' => $result['applied_coupons'],
            'has_free_shipping' => $result['has_free_shipping'],
        ];
    }
}
