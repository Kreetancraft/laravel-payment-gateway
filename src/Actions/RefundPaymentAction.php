<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Actions;

use Kreetancraft\PaymentGateway\Contracts\GatewayResolver;
use Kreetancraft\PaymentGateway\Data\RefundResult;
use Kreetancraft\PaymentGateway\Models\Payment;
use Lorisleiva\Actions\Concerns\AsAction;

class RefundPaymentAction
{
    use AsAction;

    public function __construct(
        protected GatewayResolver $resolver,
    ) {}

    public function handle(string $transactionId, float $amount): RefundResult
    {
        if (blank($transactionId)) {
            return RefundResult::failure(
                transactionId: $transactionId,
                amount: $amount,
                errorMessage: 'Transaction ID is required.',
                errorCode: 'transaction_missing'
            );
        }

        if ($amount <= 0) {
            return RefundResult::failure(
                transactionId: $transactionId,
                amount: $amount,
                errorMessage: 'Refund amount must be greater than zero.',
                errorCode: 'invalid_amount'
            );
        }

        $payment = Payment::query()->where('gateway_reference', $transactionId)->first();

        if ($payment === null) {
            return RefundResult::failure(
                transactionId: $transactionId,
                amount: $amount,
                errorMessage: "Payment with reference [{$transactionId}] not found.",
                errorCode: 'payment_not_found'
            );
        }

        $remainingCents = $payment->amount_cents - $payment->refunded_amount_cents;
        $requestedCents = (int) round($amount * 100);

        if ($requestedCents > $remainingCents) {
            return RefundResult::failure(
                transactionId: $transactionId,
                amount: $amount,
                errorMessage: "Refund amount [{$amount}] exceeds remaining refundable amount [" . ($remainingCents / 100) . "].",
                errorCode: 'amount_exceeds_balance'
            );
        }

        $gateway = $this->resolver->resolve($payment->gateway);

        if ($gateway === null) {
            return RefundResult::failure(
                transactionId: $transactionId,
                amount: $amount,
                errorMessage: "Gateway [{$payment->gateway}] is not configured or not enabled.",
                errorCode: 'gateway_not_found'
            );
        }

        $result = $gateway->refund($transactionId, $amount);

        if (! $result->success) {
            return $result;
        }

        $payment->refunded_amount_cents += $requestedCents;

        if ($payment->refunded_amount_cents >= $payment->amount_cents) {
            $payment->status = 'refunded';
            $payment->refunded_at = now();
        } else {
            $payment->status = 'partially_refunded';
            $payment->refunded_at = now();
        }

        $payment->save();

        return $result;
    }
}
