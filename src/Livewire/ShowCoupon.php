<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Livewire;

use Illuminate\Contracts\View\View;
use Kreetancraft\PaymentGateway\Layout;
use Kreetancraft\PaymentGateway\Models\Coupon;
use Kreetancraft\PaymentGateway\Models\CouponUsage;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

class ShowCoupon extends Component
{
    use WithPagination;

    public int $id;

    public function mount(int $id): void
    {
        $coupon = Coupon::findOrFail($id);
        $this->authorize('view', $coupon);
        $this->id = $coupon->id;
    }

    #[Title('Coupon Details - Admin')]
    public function render(): View
    {
        $coupon = Coupon::findOrFail($this->id);
        $this->authorize('view', $coupon);

        $redemptions = CouponUsage::where('coupon_id', $coupon->id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        $totalDiscountCents = CouponUsage::where('coupon_id', $coupon->id)->sum('amount_discounted_cents');

        return view('payment-gateway::livewire.show-coupon', [
            'coupon' => $coupon,
            'redemptions' => $redemptions,
            'totalDiscountCents' => $totalDiscountCents,
        ])->layout(Layout::admin());
    }
}
