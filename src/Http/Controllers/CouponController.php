<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Kreetancraft\PaymentGateway\Models\Coupon;
use Kreetancraft\PaymentGateway\Services\CouponService;
use Kreetancraft\PaymentGateway\Rules\ValidCouponCode;

class CouponController extends Controller
{
    public function __construct(
        private readonly CouponService $couponService,
    ) {}

    public function validateCoupon(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string', new ValidCouponCode()],
            'amount_cents' => ['nullable', 'integer', 'min:1'],
            'currency' => ['nullable', 'string', 'size:3'],
        );

        $coupon = Coupon::where('code', $request->string('code'))->first();

        if (!$coupon) {
            return response()->json([
                'valid' => false,
                'message' => 'Invalid coupon code.',
            ], 422);
        }

        $valid = $coupon->isValid(
            auth()->id(),
            $request->integer('amount_cents'),
            $request->string('currency', 'USD')
        );

        return response()->json([
            'valid' => $valid,
            'coupon' => $valid ? [
                'code' => $coupon->code,
                'label' => $coupon->label,
                'type' => $coupon->type,
                'value' => $coupon->value,
            ] : null,
            'message' => $valid ? 'Coupon is valid.' : 'Coupon is not valid.',
        ]);
    }

    public function applyCoupon(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string', new \Kreetancraft\PaymentGateway\Rules\ValidCouponCode()],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3'],
        );

        $result = app(\Kreetancraft\PaymentGateway\Services\CouponService::class)->apply(
            $request->string('code'),
            auth()->id(),
            $request->integer('amount_cents'),
            $request->string('currency')
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

    public function listCoupons(): \Illuminate\Http\JsonResponse
    {
        $coupons = Coupon::where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->get()
            ->map(fn ($c) => [
                'code' => $c->code,
                'label' => $c->label,
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