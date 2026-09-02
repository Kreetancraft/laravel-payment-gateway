<?php

namespace Kreetancraft\PaymentGateway\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Kreetancraft\PaymentGateway\Actions\VerifyPaymentAction;
use Kreetancraft\PaymentGateway\Enums\PaymentStatus;
use Kreetancraft\PaymentGateway\Models\Payment;
use Throwable;

/**
 * Ask the gateway what happened, without waiting for it to tell us.
 *
 * A buyer can close the tab before the return redirect fires, and a bank
 * notification can be dropped. `payment-gateway:reconcile` sweeps on a schedule
 * and catches those eventually, but "eventually" is however long the schedule
 * is — and in the meantime somebody has paid for an order that has not
 * completed. This closes the gap for the common case: dispatched when a checkout
 * redirects, it asks again a couple of minutes later, then at five, then at ten.
 *
 * The monolith runs the same shape as ReverifyHblTransactionJob.
 */
class ReverifyPaymentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Roughly 2, 5 and 10 minutes. Long enough that the buyer's own return wins
     * the race in the normal case, short enough to matter.
     *
     * @var list<int>
     */
    public array $backoff = [120, 300, 600];

    public int $tries = 4;

    public function __construct(public int $paymentId) {}

    public function handle(): void
    {
        $payment = Payment::find($this->paymentId);

        if ($payment === null) {
            return;
        }

        // Terminal already — the callback arrived, or an earlier attempt settled
        // it. Stop rather than asking the bank again.
        if ($payment->status !== PaymentStatus::Pending) {
            return;
        }

        if (blank($payment->gateway_reference)) {
            return;
        }

        try {
            VerifyPaymentAction::run([
                'gateway' => $payment->gateway,
                'order_no' => $payment->gateway_reference,
                'transaction_id' => $payment->gateway_reference,
            ]);
        } catch (Throwable $e) {
            // Let the queue's own backoff handle it. Verification treats an
            // unreachable gateway as undetermined, so nothing has been written
            // off by getting here.
            $this->release($this->backoff[$this->attempts() - 1] ?? 600);

            return;
        }

        // Still pending after asking: try again later, until tries runs out and
        // the scheduled sweep takes over.
        if ($payment->fresh()->status === PaymentStatus::Pending && $this->attempts() < $this->tries) {
            $this->release($this->backoff[$this->attempts() - 1] ?? 600);
        }
    }
}
