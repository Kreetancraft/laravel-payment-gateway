<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Actions;

use Kreetancraft\PaymentGateway\Contracts\GatewayResolver;
use Kreetancraft\PaymentGateway\Data\VerificationResult;
use Kreetancraft\PaymentGateway\Enums\PaymentStatus;
use Kreetancraft\PaymentGateway\Models\Payment;
use Lorisleiva\Actions\Concerns\AsAction;

class VerifyPaymentAction
{
    use AsAction;

    public function __construct(
        protected GatewayResolver $resolver,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): VerificationResult
    {
        $lookupKey = (string) (
            $data['reference']
            ?? $data['order']
            ?? $data['order_no']
            ?? $data['orderNo']
            ?? $data['transaction_id']
            ?? $data['payment_intent_id']
            ?? ''
        );

        $payment = null;
        if (filled($lookupKey)) {
            $payment = Payment::query()
                ->where('reference', $lookupKey)
                ->orWhere('gateway_reference', $lookupKey)
                ->first();
        }

        $gatewayCode = (string) ($data['gateway'] ?? ($payment?->gateway ?? ''));

        if (blank($gatewayCode)) {
            $gatewayCode = (string) ($this->resolver->getDefaultDriver() ?? 'stripe');
        }

        $gateway = $this->resolver->resolve($gatewayCode);

        if ($gateway === null) {
            return VerificationResult::failure(
                transactionId: $lookupKey,
                errorMessage: "Gateway [{$gatewayCode}] is not configured or not enabled.",
                reference: $payment?->reference ?? $lookupKey,
            );
        }

        $result = $gateway->verify($data);

        // Update database Payment model status accordingly
        if ($payment !== null) {
            $this->updatePaymentRecord($payment, $result);
        }

        return $result;
    }

    private function updatePaymentRecord(Payment $payment, VerificationResult $result): void
    {
        $statusStr = strtolower($result->status);

        $payment->status = match (true) {
            $result->success && in_array($statusStr, ['completed', 'settled', 'success', 'approved', 'succeeded', 'paid', '0000'], true) => PaymentStatus::Succeeded,
            in_array($statusStr, ['cancelled', 'canceled'], true) => PaymentStatus::Cancelled,
            ! $result->success || in_array($statusStr, ['failed', 'declined', 'rejected', 'error'], true) => PaymentStatus::Failed,
            default => $payment->status,
        };

        if ($payment->status === PaymentStatus::Succeeded) {
            $payment->paid_at ??= now();
        }

        if (filled($result->transactionId) && blank($payment->gateway_reference)) {
            $payment->gateway_reference = $result->transactionId;
        }

        $payment->save();
    }
}
