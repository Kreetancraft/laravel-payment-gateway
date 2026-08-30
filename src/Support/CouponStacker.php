<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Support;

use Kreetancraft\PaymentGateway\Models\Coupon;
use Illuminate\Support\Collection;

/**
 * Smart coupon stacking logic (from binafy/laravel-discount)
 * 
 * Rules:
 * - Free shipping always applies on top of other discounts
 * - For monetary coupons, find combination that saves customer the most
 * - Respects is_stackable flag
 * - Free shipping always stacks on top of monetary discounts
 */
class CouponStacker
{
    public function apply(array $couponCodes, int $amountCents, string $currency): array
    {
        $coupons = \Kreetancraft\PaymentGateway\Models\Coupon::whereIn('code', $couponCodes)
            ->get();

        if ($coupons->isEmpty()) {
            return [
                'discount_cents' => 0,
                'final_amount_cents' => $amountCents,
                'applied_coupons' => [],
                'has_free_shipping' => false,
            ];
        }

        $validCoupons = $coupons->filter(fn ($c) => $c->canApply(null, $amountCents, $currency));

        if ($validCoupons->isEmpty()) {
            return [
                'discount_cents' => 0,
                'final_amount_cents' => $amountCents,
                'applied_coupons' => [],
                'has_free_shipping' => false,
            ];
        }

        // Free shipping always stacks on top
        $freeShipping = $validCoupons->filter(fn ($c) => $c->is_free_shipping);
        $monetaryCoupons = $validCoupons->filter(fn ($c) => !$c->is_free_shipping);

        $hasFreeShipping = $freeShipping->isNotEmpty();

        // Find best combination of monetary coupons
        $bestCombo = $this->findBestCombination($monetaryCoupons, $amountCents);

        $discountCents = $bestCombo['discount'];
        $appliedCoupons = $bestCombo['coupons'];

        // Free shipping always stacks on top
        $hasFreeShipping = $freeShipping->isNotEmpty();

        return [
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
            'coupons' => collect($combo),
        ];
    }
}