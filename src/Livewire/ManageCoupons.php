<?php

namespace Kreetancraft\PaymentGateway\Livewire;

use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Kreetancraft\PaymentGateway\Layout;
use Kreetancraft\PaymentGateway\Models\Coupon;
use Kreetancraft\PaymentGateway\Models\CouponUsage;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManageCoupons extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $typeFilter = '';

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

    public function updatingTypeFilter(): void
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
        $this->typeFilter = '';
        $this->statusFilter = '';
        $this->resetPage();
    }

    public function sortBy(string $column): void
    {
        if ($this->sort === $column) {
            $this->direction = $this->direction === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sort = $column;
            $this->direction = 'asc';
        }
    }

    public function duplicate(int $id): void
    {
        $coupon = Coupon::findOrFail($id);
        $this->authorize('create', Coupon::class);

        $newCoupon = $coupon->replicate([
            'uuid',
            'code',
            'usage_count',
            'created_at',
            'updated_at',
        ]);

        $newCoupon->uuid = (string) Str::uuid();
        $newCoupon->code = strtoupper($coupon->code.'-COPY-'.Str::random(3));
        $newCoupon->name = ($coupon->name ?? $coupon->code).' (Copy)';
        $newCoupon->usage_count = 0;
        $newCoupon->save();

        if (class_exists(Flux::class) && app()->bound('flux')) {
            Flux::toast(variant: 'success', text: __('Coupon duplicated as [:code].', ['code' => $newCoupon->code]));
        }
    }

    public function delete(int $id): void
    {
        $coupon = Coupon::findOrFail($id);
        $this->authorize('delete', $coupon);

        $code = $coupon->code;
        $coupon->delete();

        if (class_exists(Flux::class) && app()->bound('flux')) {
            Flux::toast(variant: 'success', text: __('Coupon [:code] deleted successfully.', ['code' => $code]));
        }
    }

    public function exportCsv(): StreamedResponse
    {
        $this->authorize('viewAny', Coupon::class);

        $coupons = Coupon::orderBy('created_at', 'desc')->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="coupons_'.date('Y-m-d').'.csv"',
        ];

        return response()->stream(function () use ($coupons): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Code', 'Name', 'Type', 'Value', 'Usage Count', 'Usage Limit', 'Status', 'Expires At', 'Created At']);

            foreach ($coupons as $coupon) {
                fputcsv($handle, [
                    $coupon->code,
                    $coupon->name,
                    $coupon->type,
                    $coupon->value,
                    $coupon->usage_count,
                    $coupon->usage_limit ?? 'Unlimited',
                    $coupon->is_active ? 'Active' : 'Inactive',
                    $coupon->expires_at?->format('Y-m-d H:i') ?? 'Never',
                    $coupon->created_at->format('Y-m-d H:i'),
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    #[Title('Discount Coupons - Admin')]
    public function render(): View
    {
        $this->authorize('viewAny', Coupon::class);

        $query = Coupon::query()
            ->when($this->search !== '', fn ($q) => $q->where(fn ($sub) => $sub->where('code', 'like', "%{$this->search}%")->orWhere('name', 'like', "%{$this->search}%")))
            ->when($this->typeFilter !== '', fn ($q) => $q->where('type', $this->typeFilter))
            ->when($this->statusFilter === 'active', fn ($q) => $q->where('is_active', true)->where(fn ($sq) => $sq->whereNull('expires_at')->orWhere('expires_at', '>', now())))
            ->when($this->statusFilter === 'inactive', fn ($q) => $q->where(fn ($sq) => $sq->where('is_active', false)->orWhere('expires_at', '<=', now())))
            ->orderBy($this->sort, $this->direction);

        $coupons = $query->paginate(15);
        $totalCount = Coupon::count();
        $activeCount = Coupon::where('is_active', true)->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->count();
        $totalRedemptions = CouponUsage::sum('usage_count') ?: Coupon::sum('usage_count');
        $totalDiscountCents = CouponUsage::sum('amount_discounted_cents');

        return view('payment-gateway::livewire.manage-coupons', [
            'coupons' => $coupons,
            'totalCount' => $totalCount,
            'activeCount' => $activeCount,
            'totalRedemptions' => $totalRedemptions,
            'totalDiscountCents' => $totalDiscountCents,
        ])->layout(Layout::admin());
    }
}
