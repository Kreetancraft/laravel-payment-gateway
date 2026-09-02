<?php

namespace Kreetancraft\PaymentGateway\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Kreetancraft\PaymentGateway\Models\Payment;

/**
 * A payment reached failed or canceled, once.
 *
 * The counterpart to PaymentSucceeded, on the same transition rule: releasing a
 * held seat, freeing reserved stock, telling the buyer what happened.
 */
class PaymentFailed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Payment $payment) {}
}
