<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Observers;

use Kreetancraft\PaymentGateway\Models\Payment;
use Illuminate\Support\Str;

class PaymentObserver
{
    public function creating(Payment $payment): void
    {
        if (blank($payment->uuid)) {
            $payment->uuid = (string) Str::uuid();
        }

        if (blank($payment->reference)) {
            $payment->reference = 'PMT-' . now()->format('ymd') . '-' . Str::upper(Str::random(6));
        }

        if (blank($payment->idempotency_key)) {
            $payment->idempotency_key = hash('sha256', $payment->uuid . $payment->amount_cents . $payment->currency);
        }
    }

    public function saved(Payment $payment): void
    {
        if ($payment->wasChanged('status') && $payment->status === 'succeeded') {
            // Could dispatch event or update related invoice/bookings
        }
    }
}
