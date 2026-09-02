<?php

namespace Kreetancraft\PaymentGateway\Livewire;

use Flux\Flux;
use Illuminate\Contracts\View\View;
use Kreetancraft\PaymentGateway\Layout;
use Kreetancraft\PaymentGateway\Models\Coupon;
use Livewire\Attributes\Title;
use Livewire\Component;

class EditCoupon extends Component
{
    public int $id;

    public string $code = '';

    public string $name = '';

    public string $description = '';

    public string $type = 'percentage';

    public int $value = 10;

    public ?int $maxDiscountAmount = null;

    public ?int $minOrderAmount = null;

    public ?int $usageLimit = null;

    public ?int $usageLimitPerUser = null;

    public string $userIdsInput = '';

    public ?string $startsAt = null;

    public ?string $expiresAt = null;

    public bool $isActive = true;

    public bool $isStackable = false;

    public bool $isFreeShipping = false;

    public int $usageCount = 0;

    public function mount(int $id): void
    {
        $coupon = Coupon::findOrFail($id);
        $this->authorize('update', $coupon);

        $this->id = $coupon->id;
        $this->code = $coupon->code;
        $this->name = $coupon->name ?? '';
        $this->description = $coupon->description ?? '';
        $this->type = $coupon->type;
        $this->value = $coupon->value;
        $this->maxDiscountAmount = $coupon->max_discount_amount;
        $this->minOrderAmount = $coupon->min_order_amount;
        $this->usageLimit = $coupon->usage_limit;
        $this->usageLimitPerUser = $coupon->usage_limit_per_user;
        $this->userIdsInput = implode(', ', $coupon->user_ids ?? []);
        $this->startsAt = $coupon->starts_at?->format('Y-m-d\TH:i');
        $this->expiresAt = $coupon->expires_at?->format('Y-m-d\TH:i');
        $this->isActive = (bool) $coupon->is_active;
        $this->isStackable = (bool) $coupon->is_stackable;
        $this->isFreeShipping = (bool) $coupon->is_free_shipping;
        $this->usageCount = (int) $coupon->usage_count;
    }

    public function save(): void
    {
        $coupon = Coupon::findOrFail($this->id);
        $this->authorize('update', $coupon);

        $this->validate([
            'code' => ['required', 'string', 'max:50', 'unique:coupons,code,'.$this->id],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:percentage,fixed,buy_x_get_y,tiered,free_shipping'],
            'value' => ['required', 'integer', 'min:0'],
            'maxDiscountAmount' => ['nullable', 'integer', 'min:0'],
            'minOrderAmount' => ['nullable', 'integer', 'min:0'],
            'usageLimit' => ['nullable', 'integer', 'min:1'],
            'usageLimitPerUser' => ['nullable', 'integer', 'min:1'],
            'userIdsInput' => ['nullable', 'string'],
            'startsAt' => ['nullable', 'date'],
            'expiresAt' => ['nullable', 'date', 'after_or_equal:startsAt'],
            'isActive' => ['boolean'],
            'isStackable' => ['boolean'],
            'isFreeShipping' => ['boolean'],
        ]);

        $userIds = null;
        if (filled($this->userIdsInput)) {
            $userIds = array_map('intval', array_filter(explode(',', $this->userIdsInput)));
        }

        $coupon->update([
            'code' => strtoupper(trim($this->code)),
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'value' => $this->type === 'free_shipping' ? 0 : $this->value,
            'max_discount_amount' => $this->maxDiscountAmount,
            'min_order_amount' => $this->minOrderAmount,
            'usage_limit' => $this->usageLimit,
            'usage_limit_per_user' => $this->usageLimitPerUser,
            'user_ids' => $userIds,
            // Blank means "no schedule", not "now". Livewire binds an empty
            // date input as '', and the datetime cast reads '' as the current
            // time — so a coupon saved with the optional schedule left alone
            // came out already expired and could never be redeemed.
            'starts_at' => filled($this->startsAt) ? $this->startsAt : null,
            'expires_at' => filled($this->expiresAt) ? $this->expiresAt : null,
            'is_active' => $this->isActive,
            'is_stackable' => $this->isStackable,
            'is_free_shipping' => $this->isFreeShipping || $this->type === 'free_shipping',
        ]);

        if (class_exists(Flux::class) && app()->bound('flux')) {
            Flux::toast(variant: 'success', text: __('Coupon [:code] updated successfully.', ['code' => $this->code]));
        }

        $this->redirect(route(config('payment-gateway.routes.names.coupons', 'admin.payment.coupons')), navigate: true);
    }

    #[Title('Edit Coupon - Admin')]
    public function render(): View
    {
        return view('payment-gateway::livewire.edit-coupon')->layout(Layout::admin());
    }
}
