<?php

namespace Kreetancraft\PaymentGateway\Support;

use Illuminate\Support\Collection;
use Kreetancraft\PaymentGateway\Models\Coupon;

/**
 * Smart coupon stacking logic (from binafy/laravel-discount)
 *
 * Rules:
 * - Free shipping always applies on top of other discounts
 * - For monetary coupons, find combination that saves customer the most
 * - Respects is_stackable flag: non-stackable coupons cannot be combined together
 */
class CouponStacker
{
    public function apply(array $couponCodes, int $amountCents, string $currency): array
    {
        $coupons = Coupon::whereIn('code', $couponCodes)->get();

        if ($coupons->isEmpty()) {
            return [
                'discount_cents' => 0,
                'final_amount_cents' => $amountCents,
                'applied_coupons' => collect([]),
                'has_free_shipping' => false,
            ];
        }

        $validCoupons = $coupons->filter(fn (Coupon $c): bool => $c->canApply(null, $amountCents, $currency));

        if ($validCoupons->isEmpty()) {
            return [
                'discount_cents' => 0,
                'final_amount_cents' => $amountCents,
                'applied_coupons' => collect([]),
                'has_free_shipping' => false,
            ];
        }

        // Free shipping always stacks on top
        $freeShipping = $validCoupons->filter(fn (Coupon $c): bool => (bool) $c->is_free_shipping || $c->type === 'free_shipping');
        $monetaryCoupons = $validCoupons->filter(fn (Coupon $c): bool => ! $c->is_free_shipping && $c->type !== 'free_shipping');

        $hasFreeShipping = $freeShipping->isNotEmpty();

        // Find best combination of monetary coupons
        $bestCombo = $this->findBestCombination($monetaryCoupons, $amountCents);

        $discountCents = $bestCombo['discount'];
        $appliedCoupons = $bestCombo['coupons'];

        if ($hasFreeShipping) {
            $appliedCoupons = $appliedCoupons->concat($freeShipping);
        }

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

        if ($count === 0) {
            return ['discount' => 0, 'coupons' => collect([])];
        }

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
            $selected = [];
            for ($j = 0; $j < $count; $j++) {
                if ($i & (1 << $j)) {
                    $selected[] = $coupons[$j];
                }
            }

            // If more than 1 coupon is selected, ALL must have is_stackable = true
            if (count($selected) > 1) {
                $allStackable = collect($selected)->every(fn (Coupon $c): bool => (bool) $c->is_stackable);
                if (! $allStackable) {
                    continue;
                }
            }

            $combo = [];
            $discount = 0;
            $remaining = $amountCents;

            foreach ($selected as $coupon) {
                if ($coupon->canApply(null, $remaining, '')) {
                    $couponDiscount = $coupon->calculateDiscount($remaining);
                    $combo[] = $coupon;
                    $discount += $couponDiscount;
                    $remaining -= $couponDiscount;
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
        $sorted = $coupons->sortByDesc(fn (Coupon $c): float => $c->calculateDiscount($amountCents) / max(1, $c->value));

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
