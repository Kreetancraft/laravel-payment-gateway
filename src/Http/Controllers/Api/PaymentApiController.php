<?php

namespace Kreetancraft\PaymentGateway\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Kreetancraft\PaymentGateway\Actions\ChargePaymentAction;
use Kreetancraft\PaymentGateway\Actions\RefundPaymentAction;
use Kreetancraft\PaymentGateway\Actions\VerifyPaymentAction;

class PaymentApiController extends Controller
{
    public function charge(Request $request): JsonResponse
    {
        $result = ChargePaymentAction::run($request->all());

        return response()->json($result, $result->success ? 200 : 422);
    }

    public function refund(Request $request): JsonResponse
    {
        $result = RefundPaymentAction::run(
            transactionId: (string) $request->input('transaction_id'),
            amount: (float) $request->input('amount')
        );

        return response()->json($result, $result->success ? 200 : 422);
    }

    public function verify(Request $request): JsonResponse
    {
        $result = VerifyPaymentAction::run($request->all());

        return response()->json($result, $result->success ? 200 : 422);
    }
}
