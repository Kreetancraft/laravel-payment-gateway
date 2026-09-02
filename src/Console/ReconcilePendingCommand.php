<?php

namespace Kreetancraft\PaymentGateway\Console;

use Illuminate\Console\Command;
use Kreetancraft\PaymentGateway\Actions\VerifyPaymentAction;
use Kreetancraft\PaymentGateway\Enums\PaymentStatus;
use Kreetancraft\PaymentGateway\Models\Payment;
use Throwable;

/**
 * Ask the gateway about payments nobody ever told us the outcome of.
 *
 * A callback can be dropped — the bank retries, but not forever, and a buyer can
 * close the tab before the return redirect fires. Without a sweep the result is
 * the worst failure this package has: money taken, and an order that never
 * completes, with nothing in the application that knows to look.
 *
 * The monolith runs the same idea as `hbl:reconcile-stale`, alongside a queued
 * re-verify job with backoff. This is the scheduled half, which is the half that
 * catches everything the retries missed.
 *
 * Verification is idempotent and never downgrades a settled payment, so running
 * this often is safe.
 */
class ReconcilePendingCommand extends Command
{
    protected $signature = 'payment-gateway:reconcile
        {--minutes=15 : Only consider payments left pending for at least this long}
        {--limit=200 : Stop after this many, so a backlog cannot run away}';

    protected $description = 'Re-ask the gateway about payments still pending';

    public function handle(): int
    {
        $minutes = max(1, (int) $this->option('minutes'));
        $limit = max(1, (int) $this->option('limit'));

        // The delay matters: a payment created seconds ago is not stale, it is
        // in flight, and asking about it races the buyer's own return.
        $stale = Payment::query()
            ->where('status', PaymentStatus::Pending)
            ->where('created_at', '<=', now()->subMinutes($minutes))
            ->whereNotNull('gateway_reference')
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        if ($stale->isEmpty()) {
            $this->components->info('Nothing pending long enough to chase.');

            return self::SUCCESS;
        }

        $settled = 0;
        $stillPending = 0;
        $failed = 0;

        foreach ($stale as $payment) {
            try {
                VerifyPaymentAction::run([
                    'gateway' => $payment->gateway,
                    'order_no' => $payment->gateway_reference,
                    'transaction_id' => $payment->gateway_reference,
                ]);
            } catch (Throwable $e) {
                // One unreachable gateway must not stop the sweep.
                $this->components->warn("{$payment->gateway_reference}: {$e->getMessage()}");
            }

            match ($payment->fresh()->status) {
                PaymentStatus::Succeeded => $settled++,
                PaymentStatus::Failed, PaymentStatus::Canceled => $failed++,
                default => $stillPending++,
            };
        }

        $this->components->info(sprintf(
            'Checked %d: %d settled, %d resolved as failed or cancelled, %d still pending.',
            $stale->count(),
            $settled,
            $failed,
            $stillPending
        ));

        return self::SUCCESS;
    }
}
