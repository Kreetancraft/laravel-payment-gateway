<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Kreetancraft\PaymentGateway\Http\Requests\ApplyCouponRequest;
use Kreetancraft\PaymentGateway\Http\Requests\ValidateCouponRequest;
use Kreetancraft\PaymentGateway\Models\Coupon;
use Kreetancraft\PaymentGateway\Models\Gateway;
use Kreetancraft\PaymentGateway\Services\CouponService;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CouponService $couponService,
    ) {}

    public function __invoke(): View
    {
        $enabledGateways = Gateway::query()->enabled()->get();

        if ($enabledGateways->isEmpty()) {
            abort(404, 'No payment gateways are enabled.');
        }

        $gateways = $enabledGateways->map(fn (Gateway $gw): array => [
            'code' => $gw->code,
            'label' => $gw->getLabel(),
            'icon' => $gw->getIcon(),
            'currencies' => $gw->getSupportedCurrencies(),
        ])->values();

        return view('payment-gateway::livewire.checkout', [
            'gateways' => $gateways,
        ]);
    }

    public function applyCoupon(ApplyCouponRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->couponService->apply(
            $validated['code'],
            auth()->id(),
            (int) $validated['amount_cents'],
            $validated['currency']
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

    public function validateCoupon(ValidateCouponRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $coupon = Coupon::where('code', $validated['code'])->first();

        if (! $coupon) {
            return response()->json([
                'valid' => false,
                'message' => 'Invalid coupon code.',
            ], 422);
        }

        $valid = $coupon->isValid(
            auth()->id(),
            $request->integer('amount_cents'),
            $request->string('currency', 'USD')->toString()
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
}
