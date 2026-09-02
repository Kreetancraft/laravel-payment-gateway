<?php

namespace Kreetancraft\PaymentGateway\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Kreetancraft\PaymentGateway\Actions\RefundPaymentAction;
use Kreetancraft\PaymentGateway\Actions\VerifyPaymentAction;
use Kreetancraft\PaymentGateway\Contracts\GatewayResolver;
use Kreetancraft\PaymentGateway\Enums\PaymentStatus;
use Kreetancraft\PaymentGateway\Models\Payment;

class PaymentController extends Controller
{
    public function success(Request $request): View
    {
        $result = VerifyPaymentAction::run($request->all());

        if (! $result->success) {
            $isCancelled = in_array(strtolower($result->status), ['cancelled', 'canceled'], true);

            if ($isCancelled) {
                return view('payment-gateway::cancel', [
                    'result' => $result,
                ]);
            }

            return view('payment-gateway::failed', [
                'result' => $result,
                'errorMessage' => $result->errorMessage ?? 'Payment could not be verified by the gateway.',
            ]);
        }

        return view('payment-gateway::success', [
            'result' => $result,
        ]);
    }

    public function cancel(Request $request): View
    {
        $orderKey = (string) ($request->query('order') ?? $request->query('orderNo') ?? $request->query('reference') ?? '');
        if (filled($orderKey)) {
            Payment::query()
                ->where('reference', $orderKey)
                ->orWhere('gateway_reference', $orderKey)
                ->update(['status' => PaymentStatus::Canceled]);
        }

        return view('payment-gateway::cancel', [
        ]);
    }

    public function failed(Request $request): View
    {
        $orderKey = (string) ($request->query('order') ?? $request->query('orderNo') ?? $request->query('reference') ?? '');
        if (filled($orderKey)) {
            Payment::query()
                ->where('reference', $orderKey)
                ->orWhere('gateway_reference', $orderKey)
                ->update(['status' => PaymentStatus::Failed]);
        }

        return view('payment-gateway::failed', [
            'errorMessage' => $request->query('message', 'The transaction could not be completed by the payment provider.'),
        ]);
    }

    public function choose(Request $request): View
    {
        return view('payment-gateway::choose', [
            'gateways' => config('payment-gateway.gateways', []),
        ]);
    }

    public function checkout(Request $request, ?string $gateway = null): View
    {
        return view('payment-gateway::checkout', [
            'gateway' => $gateway,
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
                'transaction_id' => $result->transactionId,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'transaction_id' => $result->transactionId,
            'refunded_amount' => $result->amount,
        ]);
    }

    public function gateways(GatewayResolver $resolver): JsonResponse
    {
        $driverCodes = $resolver->getEnabledGateways();

        $drivers = collect($driverCodes)->map(function (string $code) use ($resolver): ?array {
            $config = $resolver->getGatewayConfig($code);

            if ($config === null) {
                return null;
            }

            return [
                'code' => $config->getCode(),
                'label' => $config->getLabel(),
                'icon' => $config->getIcon(),
                'currencies' => $config->getSupportedCurrencies(),
                'checkout_redirect' => $config->checkoutRedirect(),
            ];
        })->filter()->values()->all();

        return response()->json([
            'success' => true,
            'enabled' => $driverCodes,
            'gateways' => $drivers,
            'count' => count($drivers),
        ]);
    }
}
