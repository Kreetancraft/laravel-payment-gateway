<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Kreetancraft\PaymentGateway\Models\Coupon;
use Kreetancraft\PaymentGateway\Models\CouponUsage;
use Kreetancraft\PaymentGateway\Models\CouponUsage;
use Kreetancraft\PaymentGateway\Models\Payment;
use Kreetancraft\PaymentGateway\Contracts\PaymentGateway;
use Kreetancraft\PaymentGateway\Contracts\GatewayResolver;
use Kreetancraft\PaymentGateway\Data\PaymentResult;
use Kreetancraft\PaymentGateway\Data\RefundResult;
use Kreetancraft\PaymentGateway\Data\VerificationResult;
use Kreetancraft\PaymentGateway\Data\WebhookResult;
use Kreetancraft\PaymentGateway\Contracts\GatewayResolver;
use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Support\Facades\DB;

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

        $coupon = Coupon::where('code', $code)->firstOrFail();
        $discountCents = $coupon->calculateDiscount($amountCents);

        if ($discountCents <= 0) {
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
            'coupon' => [
                'code' => $coupon->code,
                'label' => $coupon->label,
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

        if (!$coupon) {
            return [
                'valid' => false,
                'message' => 'Coupon code not found.',
                'code' => 'COUPON_NOT_FOUND',
            ];
        }

        if (!$coupon->is_active) {
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

        if ($coupon->expires_at && $coupon->expires_at->isPast()) {
            return [
                'valid' => false,
                'message' => 'This coupon has expired.',
                'code' => 'COUPON_EXPIRED',
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

            if ($coupon->user_ids && !in_array($userId, $coupon->user_ids)) {
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
                'message' => "Minimum order amount is {$coupon->min_order_amount / 100}.",
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
        $coupons = Coupon::whereIn('code', $codes)->get();
        
        $validCoupons = $coupons->filter(fn ($c) => $c->canApply($userId, $amountCents, $currency))
            ->sortByDesc(fn ($c) => $c->calculateDiscount($amountCents))
            ->values();

        if ($validCoupons->isEmpty()) {
            return [
                'success' => false,
                'message' => 'No valid coupons provided.',
                'code' => 'NO_VALID_COUPONS',
            ];
        }

        // Smart stacking logic (from binafy)
        return $this->applyStacking($validCoupons, $amountCents, $currency);
    }

    private function applyStacking(Collection $coupons, int $amountCents, string $currency): array
    {
        $stackable = $coupons->filter(fn ($c) => $c->is_stackable);
        $nonStackable = $coupons->filter(fn ($c) => !$c->is_stackable);
        $freeShipping = $coupons->filter(fn ($c) => $c->is_free_shipping);
        $monetary = $coupons->filter(fn ($c) => !$c->is_free_shipping);

        // Free shipping always applies on top
        $freeShipping = $coupons->filter(fn ($c) => $c->is_free_shipping);
        $monetaryCoupons = $coupons->filter(fn ($c) => !$c->is_free_shipping);

        $hasFreeShipping = $freeShipping->isNotEmpty();

        // Find best combination of monetary coupons
        $bestCombo = $this->findBestCombination($monetaryCoupons, $amountCents);

        $discountCents = $bestCombo['discount'];
        $appliedCoupons = $bestCombo['coupons'];

        // Free shipping always stacks on top
        $hasFreeShipping = $freeShipping->isNotEmpty();

        return [
            'success' => true,
            'discount_cents' => $discountCents,
            'final_amount_cents' => max(0, $amountCents - $discountCents),
            'applied_coupons' => $appliedCoupons,
            'has_free_shipping' => $hasFreeShipping,
        ];
    }

    private function findBestCombination(Collection $coupons, int $amountCents): array
    {
        $couponsArray = $coupons->values()->all();
        $count = count($couponsArray);

        if ($count <= 8) {
            return $this->bruteForceBestCombo($couponsArray, $amountCents);
        }

        return $this->greedyBestCombo($coupons, $amountCents);
    }

    private function bruteForceBestCombo(array $coupons, int $amountCents): array
    {
        $bestDiscount = 0;
        $bestCombo = [];

        $count = count($coupons);
        
        for ($i = 1; $i < (1 << $count); $i++) {
            $combo = [];
            $discount = 0;
            $remaining = $amountCents;

            for ($j = 0; $j < $count; $j++) {
                if ($i & (1 << $j)) {
                    $coupon = $coupons[$j];
                    if ($coupon->canApply(null, $remaining, '')) {
                        $couponDiscount = $coupon->calculateDiscount($remaining);
                        $combo[] = $coupons[$j];
                        $discount += $couponDiscount;
                        $remaining -= $couponDiscount;
                    }
                }
            }

            if ($discount > $bestDiscount) {
                $bestDiscount = $discount;
                $bestCombo = $combo;
            }
        }

        return [
            'discount' => $bestDiscount,
            'coupons' => collect($bestCombo),
        ];
    }

    private function greedyBestCombo(Collection $coupons, int $amountCents): array
    {
        // Sort by discount efficiency (discount per cent of value)
        $sorted = $coupons->sortByDesc(fn ($c) => $c->calculateDiscount($amountCents) / max(1, $c->value));
        
        $discount = 0;
        $combo = [];
        $remaining = $amountCents;

        foreach ($sorted as $coupon) {
            if ($coupon->canApply(null, $remaining, '')) {
                $couponDiscount = $coupon->calculateDiscount($remaining);
                $discount += $couponDiscount;
                $remaining -= $couponDiscount;
                $combo[] = $coupon;
            }
        }

        return [
            'discount' => $discount,
            'coupons' => $combo,
        ];
    }
}