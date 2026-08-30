<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Livewire;

use Illuminate\Contracts\View\View;
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

        $result = RefundPaymentAction::run($payment);

        if ($result->successful()) {
            session()->flash('transaction_message', "Refund of \${$payment->amount} processed successfully.");
        } else {
            session()->flash('transaction_error', "Refund failed: {$result->errorMessage}");
        }
    }

    public function exportCsv(): StreamedResponse
    {
        $this->authorize('viewAny', Payment::class);

        $payments = Payment::orderBy('created_at', 'desc')->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="transactions_'.date('Y-m-d').'.csv"',
        ];

        return response()->stream(function () use ($payments): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Reference', 'Gateway', 'Amount', 'Currency', 'Status', 'Customer Email', 'Date']);

            foreach ($payments as $p) {
                fputcsv($handle, [
                    $p->reference,
                    $p->gateway,
                    $p->amount,
                    $p->currency,
                    $p->status,
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

        $query = Payment::query()
            ->when($this->search !== '', fn ($q) => $q->where(fn ($sub) => $sub->where('reference', 'like', "%{$this->search}%")->orWhere('customer_email', 'like', "%{$this->search}%")))
            ->when($this->gatewayFilter !== '', fn ($q) => $q->where('gateway', $this->gatewayFilter))
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->orderBy($this->sort, $this->direction);

        $payments = $query->paginate(15);
        $totalCount = Payment::count();
        $succeededCount = Payment::where('status', 'succeeded')->orWhere('status', 'completed')->count();
        $totalVolumeCents = Payment::whereIn('status', ['succeeded', 'completed'])->sum('amount_cents');

        return view('payment-gateway::livewire.manage-transactions', [
            'payments' => $payments,
            'totalCount' => $totalCount,
            'succeededCount' => $succeededCount,
            'totalVolumeCents' => $totalVolumeCents,
        ])->layout(Layout::admin());
    }
}
