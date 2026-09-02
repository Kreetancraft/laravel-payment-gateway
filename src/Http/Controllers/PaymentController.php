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
        $payment = $this->findPayment($request);

        if (! $result->success) {
            $isCancelled = in_array(strtolower($result->status), ['cancelled', 'canceled'], true);

            if ($isCancelled) {
                return view('payment-gateway::cancel', [
                    'result' => $result,
                    'payment' => $payment?->fresh(),
                ]);
            }

            return view('payment-gateway::failed', [
                'result' => $result,
                'payment' => $payment?->fresh(),
                'errorMessage' => $result->errorMessage ?? 'Payment could not be verified by the gateway.',
            ]);
        }

        return view('payment-gateway::success', [
            'result' => $result,
            'payment' => $payment?->fresh(),
        ]);
    }

    /**
     * The payment this return relates to, by any key a gateway might send back.
     *
     * The pages render from the record rather than from the query string, so a
     * buyer sees what was actually taken rather than what the URL claims.
     */
    private function findPayment(Request $request): ?Payment
    {
        foreach (['reference', 'order', 'order_no', 'orderNo', 'transaction_id', 'payment_intent_id', 'session_id'] as $key) {
            $value = $request->query($key);

            if (blank($value)) {
                continue;
            }

            $payment = Payment::query()
                ->where('reference', $value)
                ->orWhere('gateway_reference', $value)
                ->first();

            if ($payment !== null) {
                return $payment;
            }
        }

        return null;
    }

    public function cancel(Request $request): View
    {
        // Only an attempt that is still open may be closed from a return URL.
        // This used to update whatever matched, so anyone could hit
        // /payment/cancel?reference=PMT-… with somebody else's reference and
        // mark a settled payment cancelled — an unauthenticated write to a
        // financial record, from a query string.
        $payment = $this->findPayment($request);

        if ($payment !== null && $payment->status === PaymentStatus::Pending) {
            $payment->update(['status' => PaymentStatus::Canceled]);
        }

        return view('payment-gateway::cancel', [
            'payment' => $payment?->fresh(),
        ]);
    }

    public function failed(Request $request): View
    {
        // Same guard as cancel(): a settled payment is never failed by a URL.
        $payment = $this->findPayment($request);

        if ($payment !== null && $payment->status === PaymentStatus::Pending) {
            $payment->update(['status' => PaymentStatus::Failed]);
        }

        return view('payment-gateway::failed', [
            'payment' => $payment?->fresh(),
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
