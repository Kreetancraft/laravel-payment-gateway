<?php

namespace Kreetancraft\PaymentGateway\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Kreetancraft\PaymentGateway\Actions\HandleWebhookAction;

class WebhookController extends Controller
{
    public function __invoke(Request $request, string $gateway): JsonResponse
    {
        $result = HandleWebhookAction::run(
            gateway: $gateway,
            request: $request,
        );

        if (! $result->success) {
            return response()->json([
                'success' => false,
                'message' => $result->errorMessage,
                'gateway' => $gateway,
            ], 400);
        }

        return response()->json([
            'success' => true,
            'gateway' => $gateway,
            'event_type' => $result->eventType,
            'transaction_id' => $result->transactionId,
            'status' => $result->status,
        ]);
    }
}
