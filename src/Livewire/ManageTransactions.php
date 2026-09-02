<?php

namespace Kreetancraft\PaymentGateway\Livewire;

use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Kreetancraft\PaymentGateway\Actions\RefundPaymentAction;
use Kreetancraft\PaymentGateway\Layout;
use Kreetancraft\PaymentGateway\Models\Payment;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManageTransactions extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $gatewayFilter = '';

    #[Url(except: '')]
    public string $statusFilter = '';

    #[Url(except: 'created_at')]
    public string $sort = 'created_at';

    #[Url(except: 'desc')]
    public string $direction = 'desc';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingGatewayFilter(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->gatewayFilter = '';
        $this->statusFilter = '';
        $this->resetPage();
    }

    public function refund(int $paymentId): void
    {
        $payment = Payment::findOrFail($paymentId);
        $this->authorize('refund', $payment);

        $result = RefundPaymentAction::forPayment($payment);

        if (! class_exists(Flux::class) || ! app()->bound('flux')) {
            return;
        }

        if (! $result->success) {
            Flux::toast(variant: 'danger', text: __('Refund failed: :error', ['error' => $result->errorMessage]));

            return;
        }

        // `$result->successful()` does not exist on RefundResult, and Data has no
        // __call, so pressing Refund threw "Call to undefined method" every time
        // — the only refund button in the package could never have worked. The
        // suite missed it because the tests call the action directly and never
        // drive this component.
        //
        // `$payment->amount` does not exist either, so the message read
        // "Refund of $ processed successfully."
        Flux::toast(variant: 'success', text: __('Refund of :amount processed successfully.', [
            'amount' => strtoupper($payment->currency).' '.number_format($result->amount, 2),
        ]));
    }

    /**
     * The list the admin is actually looking at.
     *
     * Shared with the export, which used to ignore every filter and hand back
     * the whole table however the screen was narrowed — so "Export CSV" on a
     * search for one customer produced a file containing everyone.
     *
     * @return Builder<Payment>
     */
    private function filteredQuery(): Builder
    {
        return Payment::query()
            ->when($this->search !== '', fn (Builder $q) => $q->where(
                fn (Builder $sub) => $sub->where('reference', 'like', "%{$this->search}%")
                    ->orWhere('customer_email', 'like', "%{$this->search}%")
            ))
            ->when($this->gatewayFilter !== '', fn (Builder $q) => $q->where('gateway', $this->gatewayFilter))
            ->when($this->statusFilter !== '', fn (Builder $q) => $q->where('status', $this->statusFilter))
            ->orderBy($this->sortColumn(), $this->sortDirection())
            // `created_at` is not unique — a burst of payments shares a second —
            // so without a tiebreaker the same row can appear on two pages.
            ->orderBy('id', $this->sortDirection());
    }

    /**
     * Sorting is driven from the URL, so the column cannot go straight into the
     * query: an unknown one is a 500, and a column name is not something a
     * visitor should get to choose.
     */
    private function sortColumn(): string
    {
        $sortable = ['created_at', 'amount_cents', 'status', 'gateway', 'reference', 'paid_at'];

        return in_array($this->sort, $sortable, true) ? $this->sort : 'created_at';
    }

    private function sortDirection(): string
    {
        return $this->direction === 'asc' ? 'asc' : 'desc';
    }

    public function exportCsv(): StreamedResponse
    {
        $this->authorize('viewAny', Payment::class);

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="transactions_'.date('Y-m-d').'.csv"',
        ];

        $query = $this->filteredQuery();

        return response()->stream(function () use ($query): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Reference', 'Gateway', 'Amount', 'Currency', 'Status', 'Customer Email', 'Date']);

            // `lazy()` inside the closure, not `get()` outside it. Every payment
            // this application has ever taken was loaded into memory before a
            // single byte was sent, which is fine on a bench and fatal on a
            // table with a year of transactions in it.
            foreach ($query->lazy() as $p) {
                fputcsv($handle, [
                    $p->reference,
                    $p->gateway,
                    // Not `$p->amount` — there is no such attribute, so this
                    // column was blank in every CSV ever exported.
                    number_format($p->amount_cents / 100, 2, '.', ''),
                    $p->currency,
                    // The enum itself, not its value, was written here — and
                    // fputcsv cannot convert an enum to a string, so the export
                    // did not merely lose a column, it threw.
                    $p->status->value,
                    $p->customer_email ?? 'N/A',
                    $p->created_at->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    #[Title('Payment Transactions - Admin')]
    public function render(): View
    {
        $this->authorize('viewAny', Payment::class);

        $payments = $this->filteredQuery()->paginate(15);
        // Three separate table scans became one pass.
        $stats = Payment::query()
            ->selectRaw('count(*) as total_count')
            ->selectRaw("sum(case when status in ('succeeded', 'completed') then 1 else 0 end) as succeeded_count")
            ->selectRaw("sum(case when status in ('succeeded', 'completed') then amount_cents else 0 end) as volume_cents")
            ->first();

        $totalCount = (int) ($stats->total_count ?? 0);
        $succeededCount = (int) ($stats->succeeded_count ?? 0);
        $totalVolumeCents = (int) ($stats->volume_cents ?? 0);

        return view('payment-gateway::livewire.manage-transactions', [
            'payments' => $payments,
            'totalCount' => $totalCount,
            'succeededCount' => $succeededCount,
            'totalVolumeCents' => $totalVolumeCents,
        ])->layout(Layout::admin());
    }
}
