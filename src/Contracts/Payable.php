<?php

namespace Kreetancraft\PaymentGateway\Contracts;

/**
 * Something that can be paid for.
 *
 * The host implements this on whatever it sells — an invoice, a booking, an
 * order. This package never learns what that is; it only asks how much is owed
 * and in what currency.
 *
 * It exists because the amount used to come from the request. `POST /checkout`
 * is public and `ChargePaymentAction` validated `amount_cents` as
 * `required|integer|min:1`, so the buyer chose the price, and `Payment` had no
 * link to what was being bought — nothing to check an amount against even if
 * somebody had wanted to.
 *
 * The monolith avoids this by putting checkout on the resource
 * (`invoices/{token}/pay`, `bookings/{token}/pay`) and reading the amount off
 * it server-side; the request may only pick *which* server-computed amount:
 *
 *     $amountCents = $request->validated('amount') === 'deposit'
 *         ? max(0, $invoice->deposit_amount_cents - $invoice->amount_paid_cents)
 *         : $invoice->balance_due_cents;
 *
 * This is the same idea in a form a package can offer: name the thing, and the
 * thing says what it costs.
 */
interface Payable
{
    /**
     * What is still owed, in minor units.
     *
     * Return what remains, not the original total — this is what will be
     * charged, and a partly-paid payable must not be charged twice over.
     */
    public function paymentAmountCents(): int;

    /**
     * ISO-4217, e.g. `NPR`. Never taken from the request: charging the same
     * number in a different currency is how a USD invoice gets settled for a
     * fraction of its value.
     */
    public function paymentCurrency(): string;

    /**
     * A stable, human-recognisable reference for this payable — an invoice
     * number, a booking code. Used as the idempotency seed, so it must not
     * change between attempts to pay the same thing.
     */
    public function paymentReference(): string;

    /**
     * What the buyer sees on their statement and receipt.
     */
    public function paymentDescription(): ?string;
}
