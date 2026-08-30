<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Kreetancraft\PaymentGateway\Actions\ChargePaymentAction;

class CheckoutController extends Controller
{
    public function apiCheckout(Request $request): JsonResponse
    {
        $result = ChargePaymentAction::run($request->all());

        if (! $result->success) {
            return response()->json([
                'success' => false,
                'message' => $result->errorMessage,
                'code' => $result->errorCode,
                'order_reference' => $result->orderReference,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'order_reference' => $result->orderReference,
            'redirect_url' => $result->redirectUrl,
            'checkout_data' => $result->checkoutData,
        ]);
    }
}
