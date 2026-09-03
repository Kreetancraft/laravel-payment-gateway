<?php

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
        // `??` only falls through on null, and these arrive from a query string
        // where an unset value is an empty *string*. A Stripe return carrying
        // `?reference=&session_id=cs_test_...` therefore stopped at the blank
        // reference and never looked at the session id — which was not in this
        // list at all — so no payment was found.
        $lookupKey = $this->firstFilled($data, [
            'reference', 'order', 'order_no', 'orderNo',
            'transaction_id', 'payment_intent_id', 'session_id',
        ]);

        $payment = null;
        if (filled($lookupKey)) {
            $payment = Payment::query()
                ->where('reference', $lookupKey)
                ->orWhere('gateway_reference', $lookupKey)
                ->first();
        }

        // The payment's own gateway wins. It used to be the other way round, and
        // `/payment/success` is a public GET — so anyone with a reference could
        // add `?gateway=stripe` to an HBL payment, have Stripe fail to recognise
        // the id, and turn a settled payment into a failed one. A request does
        // not get to choose who is asked about somebody else's money.
        $gatewayCode = (string) ($payment?->gateway ?? '') ?: $this->firstFilled($data, ['gateway']);

        // Only guess when there is genuinely nothing to go on. With a lookup key
        // that matched nothing, falling back to the default driver handed a
        // Stripe session id to the bank's verifier — the query log showed
        // `payment_gateways where code = 'himalayan'` on a Stripe return — and
        // the buyer was told their payment had failed.
        if (blank($gatewayCode) && filled($lookupKey)) {
            return VerificationResult::failure(
                transactionId: $lookupKey,
                errorMessage: 'No payment matches that reference, so there is no gateway to ask about it.',
                reference: $lookupKey,
            );
        }

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

    /**
     * The first of these keys with a non-blank value.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>  $keys
     */
    private function firstFilled(array $data, array $keys): string
    {
        foreach ($keys as $key) {
            if (filled($data[$key] ?? null)) {
                return (string) $data[$key];
            }
        }

        return '';
    }

    private function updatePaymentRecord(Payment $payment, VerificationResult $result): void
    {
        $statusStr = strtolower($result->status);

        // `pending` means undetermined — the gateway could not be reached, or has
        // not settled yet. It must leave the record alone. The old third arm was
        // `! $result->success => Failed`, so any transport error permanently wrote
        // off a payment that may well have been captured.
        if ($statusStr === 'pending') {
            return;
        }

        $target = match (true) {
            $result->success && in_array($statusStr, ['completed', 'settled', 'success', 'approved', 'succeeded', 'paid', '0000'], true) => PaymentStatus::Succeeded,
            in_array($statusStr, ['cancelled', 'canceled'], true) => PaymentStatus::Canceled,
            in_array($statusStr, ['failed', 'declined', 'rejected', 'error'], true) => PaymentStatus::Failed,
            default => null,
        };

        if ($target === null) {
            return;
        }

        $extra = [];

        if (filled($result->transactionId) && blank($payment->gateway_reference)) {
            $extra['gateway_reference'] = $result->transactionId;
        }

        // Through the locked transition, not a bare save: the buyer's return page
        // and the gateway's webhook routinely arrive together, and both used to
        // write the status and fire fulfilment.
        $payment->settleTo($target, $extra);
    }
}
