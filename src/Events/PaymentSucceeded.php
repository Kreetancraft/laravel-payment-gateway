<?php

namespace Kreetancraft\PaymentGateway\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Kreetancraft\PaymentGateway\Models\Payment;

/**
 * A payment reached succeeded, once.
 *
 * This is the seam for everything that happens *because* money arrived:
 * generating the invoice, confirming the booking, sending the receipt,
 * decrementing stock.
 *
 * Two things make it trustworthy enough to hang that on.
 *
 * It fires on the *transition*, from the model, so it does not matter which
 * path settled the payment — the webhook, a manual verify, the reconcile sweep
 * or the re-verify job all funnel through the same status write. And it fires
 * only when the status actually changed, so re-saving a settled payment (the
 * webhook arriving twice, which it will) does not produce a second invoice.
 *
 * It is not fired from the return page. A buyer can pay and lose their
 * connection before the redirect lands, and that buyer still needs an invoice.
 */
class PaymentSucceeded
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Payment $payment) {}
}
