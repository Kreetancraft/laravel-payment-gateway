<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Kreetancraft\PaymentGateway\Actions\RefundPaymentAction;
use Kreetancraft\PaymentGateway\Actions\VerifyPaymentAction;
use Kreetancraft\PaymentGateway\Contracts\GatewayResolver;

class PaymentController extends Controller
{
    public function success(Request $request): View
    {
        $result = VerifyPaymentAction::run($request->all());

        return view('payment-gateway::success', [
            'result' => $result,
            'payload' => $request->all(),
        ]);
    }

    public function cancel(Request $request): View
    {
        return view('payment-gateway::cancel', [
            'payload' => $request->all(),
        ]);
    }

    public function choose(Request $request): View
    {
        return view('payment-gateway::choose', [
            'gateways' => config('payment-gateway.gateways', []),
            'payload' => $request->all(),
        ]);
    }

    public function checkout(Request $request, ?string $gateway = null): View
    {
        return view('payment-gateway::checkout', [
            'gateway' => $gateway,
            'payload' => $request->all(),
        ]);
    }

    public function verify(Request $request): JsonResponse
    {
        $result = VerifyPaymentAction::run($request->all());

        if (! $result->success) {
            return response()->json([
                'success' => false,
                'message' => $result->errorMessage,
                'transaction_id' => $result->transactionId,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'transaction_id' => $result->transactionId,
            'status' => $result->status,
            'amount' => $result->amount,
            'currency' => $result->currency,
            'paid_at' => $result->paidAt,
        ]);
    }

    public function refund(Request $request): JsonResponse
    {
        $transactionId = (string) $request->input('transaction_id', $request->input('order_no', ''));

        if (blank($transactionId)) {
            return response()->json([
                'success' => false,
                'message' => 'transaction_id is required.',
            ], 422);
        }

        $amount = (float) $request->input('amount', 0);

        if ($amount <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Refund amount must be greater than zero.',
            ], 422);
        }

        $result = RefundPaymentAction::run(
            transactionId: $transactionId,
            amount: $amount,
        );

        if (! $result->success) {
            return response()->json([
                'success' => false,
                'message' => $result->errorMessage,
                'code' => $result->errorCode,
                'transaction_id' => $result->transactionId,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'transaction_id' => $result->transactionId,
            'amount' => $result->amount,
            'refund_id' => $result->refundId,
        ]);
    }

    public function gateways(GatewayResolver $resolver): JsonResponse
    {
        $enabled = $resolver->getEnabledGateways();

        $gateways = collect(config('payment-gateway.gateways', []))
            ->map(function (array $config, string $code) use ($resolver, $enabled): array {
                $gatewayConfig = $resolver->getGatewayConfig($code);

                if ($gatewayConfig === null) {
                    return [
                        'code' => $code,
                        'label' => $config['label'] ?? $code,
                        'enabled' => in_array($code, $enabled, true),
                        'currencies' => $config['currencies'] ?? [],
                    ];
                }

                return [
                    'code' => $gatewayConfig->getCode(),
                    'label' => $gatewayConfig->getLabel(),
                    'icon' => $gatewayConfig->getIcon(),
                    'enabled' => in_array($code, $enabled, true),
                    'currencies' => $gatewayConfig->getSupportedCurrencies(),
                    'capabilities' => $gatewayConfig->getCapabilities(),
                    'checkout_redirect' => $gatewayConfig->checkoutRedirect(),
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'enabled' => $enabled,
            'gateways' => $gateways,
        ]);
    }
}
