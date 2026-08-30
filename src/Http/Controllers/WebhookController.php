<?php

declare(strict_types=1);

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
            payload: $request->all() ?: (array) json_decode($request->getContent() ?: '[]', true),
            headers: $request->headers->all(),
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

    public function handle(Request $request, string $gateway): JsonResponse
    {
        return $this->__invoke($request, $gateway);
    }
}
