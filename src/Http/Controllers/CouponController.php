<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Kreetancraft\PaymentGateway\Http\Requests\ApplyCouponRequest;
use Kreetancraft\PaymentGateway\Http\Requests\ValidateCouponRequest;
use Kreetancraft\PaymentGateway\Models\Coupon;
use Kreetancraft\PaymentGateway\Services\CouponService;

class CouponController extends Controller
{
    public function __construct(
        private readonly CouponService $couponService,
    ) {}

    public function validateCoupon(ValidateCouponRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $coupon = Coupon::where('code', $validated['code'])->first();

        if (! $coupon) {
            return response()->json([
                'valid' => false,
                'message' => 'Invalid coupon code.',
            ]);
        }

        $valid = $coupon->isValid(
            auth()->id(),
            isset($validated['amount_cents']) ? (int) $validated['amount_cents'] : null,
            (string) ($validated['currency'] ?? 'USD')
        );

        if (! $valid) {
            return response()->json([
                'valid' => false,
                'message' => 'Coupon is not valid.',
            ]);
        }

        return response()->json([
            'valid' => true,
            'coupon' => [
                'code' => $coupon->code,
                'name' => $coupon->name,
                'type' => $coupon->type,
                'value' => $coupon->value,
            ],
            'message' => 'Coupon is valid.',
        ]);
    }

    public function applyCoupon(ApplyCouponRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->couponService->apply(
            (string) $validated['code'],
            auth()->id(),
            (int) $validated['amount_cents'],
            (string) $validated['currency']
        );

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'code' => $result['code'],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'discount_cents' => $result['discount_cents'],
            'final_amount_cents' => $result['final_amount_cents'],
            'coupon' => $result['coupon'],
            'has_free_shipping' => $result['has_free_shipping'] ?? false,
        ]);
    }

    public function listCoupons(): JsonResponse
    {
        $coupons = Coupon::query()
            ->active()
            ->get()
            ->map(fn (Coupon $c): array => [
                'code' => $c->code,
                'name' => $c->name,
                'type' => $c->type,
                'value' => $c->value,
                'max_discount_amount' => $c->max_discount_amount,
                'min_order_amount' => $c->min_order_amount,
                'is_stackable' => $c->is_stackable,
                'is_free_shipping' => $c->is_free_shipping,
            ]);

        return response()->json([
            'coupons' => $coupons,
        ]);
    }
}
