<?php

namespace Kreetancraft\PaymentGateway\Actions;

use Illuminate\Support\Facades\DB;
use Kreetancraft\PaymentGateway\Contracts\GatewayResolver;
use Kreetancraft\PaymentGateway\Data\RefundResult;
use Kreetancraft\PaymentGateway\Enums\PaymentStatus;
use Kreetancraft\PaymentGateway\Models\Payment;
use Lorisleiva\Actions\Concerns\AsAction;

class RefundPaymentAction
{
    use AsAction;

    public function __construct(
        protected GatewayResolver $resolver,
    ) {}

    /**
     * Refund a Payment, defaulting to whatever is still outstanding on it.
     *
     * The transactions screen is the only authorized way to refund in this
     * package, and it was calling `run($payment)` — a model into a
     * `string $transactionId` parameter, with no amount — so it threw an
     * ArgumentCountError every time somebody pressed the button. Meanwhile an
     * unauthenticated API route called the same action correctly. This gives
     * the screen an entry point shaped the way it actually calls.
     */
    public static function forPayment(Payment $payment, ?float $amount = null): RefundResult
    {
        return static::run(
            transactionId: (string) $payment->gateway_reference,
            amount: $amount ?? ($payment->amount_cents - $payment->refunded_amount_cents) / 100,
        );
    }

    /**
     * Refund a payment by its gateway reference.
     *
     * The parameter name is part of the API — callers use named arguments — so
     * it stays. `$amount` is now optional and refunds nothing by default rather
     * than being required; pass it, or use forPayment() below.
     */
    public function handle(string $transactionId, ?float $amount = null): RefundResult
    {
        $amount ??= 0.0;

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

        // Only a payment that was actually taken can be given back. Without this
        // a pending or failed row with a gateway reference could be "refunded"
        // and stamped Refunded, inventing money that never arrived.
        if (! in_array($payment->status, [PaymentStatus::Succeeded, PaymentStatus::PartiallyRefunded], true)) {
            return RefundResult::failure(
                transactionId: $transactionId,
                amount: $amount,
                errorMessage: "A payment with status [{$payment->status->value}] cannot be refunded.",
                errorCode: 'not_refundable'
            );
        }

        // Read the balance under a lock and hold it across the gateway call.
        // Two refunds started together both used to read `refunded_amount_cents`
        // as 0, both pass this check, and both reach the provider — two refunds
        // left the account and the table recorded one.
        $requestedCents = (int) round($amount * 100);

        $remainingCents = DB::transaction(function () use ($payment): int {
            $locked = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->first();

            return $locked === null ? 0 : $locked->amount_cents - $locked->refunded_amount_cents;
        });

        if ($requestedCents > $remainingCents) {
            $formattedRemaining = $remainingCents / 100;

            return RefundResult::failure(
                transactionId: $transactionId,
                amount: $amount,
                errorMessage: "Refund amount [{$amount}] exceeds remaining refundable amount [{$formattedRemaining}].",
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

        // The provider has given the money back; record it atomically. `increment`
        // rather than read-add-write, so two refunds cannot land on the same
        // total, and the status is decided from the value the database ended up
        // with rather than the one this process happened to read.
        DB::transaction(function () use ($payment, $requestedCents): void {
            $payment->increment('refunded_amount_cents', $requestedCents);
            $payment->refresh();

            $payment->settleTo(
                $payment->refunded_amount_cents >= $payment->amount_cents
                    ? PaymentStatus::Refunded
                    : PaymentStatus::PartiallyRefunded,
                ['refunded_at' => now()],
            );
        });

        return $result;
    }
}
