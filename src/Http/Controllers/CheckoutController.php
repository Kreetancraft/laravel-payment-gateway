<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Kreetancraft\PaymentGateway\Actions\ChargePaymentAction;
use Kreetancraft\PaymentGateway\Services\CouponService;
use Kreetancraft\PaymentGateway\Models\Coupon;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CouponService $couponService,
    ) {}

    public function __invoke(Request $request): View
    {
        $enabledGateways = $this->getEnabledGateways();
        
        if (empty($enabledGateways)) {
            abort(404, 'No payment gateways are enabled.');
        }

        // Check if coupon code is provided in query string
        $couponCode = $request->query('coupon');
        $coupon = null;
        
        if ($couponCode) {
            $coupon = Coupon::where('code', $couponCode)->first();
            if (!$coupon || !$coupon->isValid()) {
                session()->flash('coupon_error', 'Invalid or expired coupon code.');
            }
        }

        $gateways = collect(config('payment-gateway.gateways', []))
            ->filter(fn ($config, $code) => !empty(config("payment-gateway.gateways.{$code}.enabled", true)))
            ->map(fn ($config, $code) => [
                'code' => $code,
                'label' => $config['label'] ?? $code,
                'icon' => $config['icon'] ?? '',
                'currencies' => $config['currencies'] ?? [],
            ])
            ->values();

        return view('payment-gateway::livewire.checkout', [
            'gateways' => $gateways,
            'coupon' => $coupon,
        ]);
    }

    public function applyCoupon(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string', new ValidCouponCode()],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3'],
        ]);

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
}