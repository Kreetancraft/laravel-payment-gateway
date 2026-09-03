<?php

namespace Kreetancraft\PaymentGateway\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Kreetancraft\PaymentGateway\Contracts\Payable;
use Kreetancraft\PaymentGateway\Contracts\SupportsDeposit;

/**
 * Stands in for a host's invoice.
 *
 * The package never sees a real one — it only asks how much is owed and in what
 * currency — so this asserts the contract by being it.
 */
class TestInvoice extends Model implements Payable, SupportsDeposit
{
    protected $table = 'test_invoices';

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
        // What is left, not the total: a partly-paid invoice must not be
        // charged its full value again.
        return max(0, (int) $this->total_cents - (int) $this->paid_cents);
    }

    /**
     * The whole deposit. What is left of it is the package's arithmetic, since
     * it holds the payment records.
     */
    public function paymentDepositCents(): int
    {
        return (int) ($this->deposit_cents ?? 0);
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
        return 'Invoice '.$this->number;
    }
}
