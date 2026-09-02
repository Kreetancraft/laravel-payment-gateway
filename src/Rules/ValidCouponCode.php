<?php

namespace Kreetancraft\PaymentGateway\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Kreetancraft\PaymentGateway\Models\Coupon;
use Kreetancraft\PaymentGateway\Models\CouponUsage;

class ValidCouponCode implements ValidationRule
{
    public function __construct(
        private ?int $userId = null,
        private ?int $amountCents = null,
        private string $currency = 'USD',
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $coupon = Coupon::where('code', $value)->first();

        if (! $coupon) {
            $fail('The coupon code is invalid.');

            return;
        }

        if (! $coupon->is_active) {
            $fail('This coupon is not active.');

            return;
        }

        if ($coupon->starts_at && $coupon->starts_at->isFuture()) {
            $fail('This coupon is not yet valid.');

            return;
        }

        if ($coupon->expires_at && $coupon->expires_at->isPast()) {
            $fail('This coupon has expired.');

            return;
        }

        if ($coupon->usage_limit && $coupon->usage_count >= $coupon->usage_limit) {
            $fail('This coupon has reached its usage limit.');

            return;
        }

        if (auth()->check() && $coupon->usage_limit_per_user) {
            $userUsage = CouponUsage::where('coupon_id', $coupon->id)
                ->where('user_id', auth()->id())
                ->sum('usage_count');

            if ($userUsage >= $coupon->usage_limit_per_user) {
                $fail('You have reached the usage limit for this coupon.');

                return;
            }
        }

        if ($coupon->user_ids && ! in_array(auth()->id(), $coupon->user_ids)) {
            $fail('This coupon is not available for your account.');

            return;
        }

        // Note: amount and currency validation would need to be passed in
        // For now, we just validate the coupon itself
    }
}
