<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Kreetancraft\PaymentGateway\Contracts\Payable;

/**
 * A stand-in for whatever a host application sells.
 *
 * The package never sees a real invoice — it asks a Payable how much is owed
 * and in what currency, and this is the smallest honest implementation of that.
 */
class DemoInvoice extends Model implements Payable
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'total_cents' => 'integer',
            'paid_cents' => 'integer',
        ];
    }

    public function paymentAmountCents(): int
    {
        return max(0, $this->total_cents - $this->paid_cents);
    }

    public function paymentCurrency(): string
    {
        return (string) $this->currency;
    }

    public function paymentReference(): string
    {
        return (string) $this->number;
    }

    public function paymentDescription(): ?string
    {
        return 'Demo invoice '.$this->number;
    }
}
