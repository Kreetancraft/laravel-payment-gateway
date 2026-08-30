<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Livewire;

use Illuminate\Contracts\View\View;
use Kreetancraft\PaymentGateway\Models\Coupon;
use Livewire\Component;
use Livewire\WithPagination;

class ManageCoupons extends Component
{
    use WithPagination;

    public bool $showCreateModal = false;

    public bool $showEditModal = false;

    public ?int $editingId = null;

    public string $code = '';

    public string $name = '';

    public string $description = '';

    public string $type = 'percentage';

    public int $value = 0;

    public ?int $maxDiscountAmount = null;

    public ?int $minOrderAmount = null;

    public ?int $usageLimit = null;

    public ?int $usageLimitPerUser = null;

    public array $userIds = [];

    public ?string $startsAt = null;

    public ?string $expiresAt = null;

    public bool $isActive = true;

    public array $conditions = [];

    public bool $isStackable = false;

    public bool $isFreeShipping = false;

    public function create(): void
    {
        $this->resetForm();
        $this->showCreateModal = true;
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->code = '';
        $this->name = '';
        $this->description = '';
        $this->type = 'percentage';
        $this->value = 0;
        $this->maxDiscountAmount = null;
        $this->minOrderAmount = null;
        $this->usageLimit = null;
        $this->usageLimitPerUser = null;
        $this->userIds = [];
        $this->startsAt = null;
        $this->expiresAt = null;
        $this->isActive = true;
        $this->conditions = [];
        $this->isStackable = false;
        $this->isFreeShipping = false;
    }

    public function save(): void
    {
        $this->validate([
            'code' => ['required', 'string', 'max:50', 'unique:coupons,code'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:percentage,fixed,buy_x_get_y,tiered,free_shipping'],
            'value' => ['required', 'integer', 'min:0'],
            'maxDiscountAmount' => ['nullable', 'integer', 'min:0'],
            'minOrderAmount' => ['nullable', 'integer', 'min:0'],
            'usageLimit' => ['nullable', 'integer', 'min:1'],
            'usageLimitPerUser' => ['nullable', 'integer', 'min:1'],
            'userIds' => ['nullable', 'array'],
            'startsAt' => ['nullable', 'date'],
            'expiresAt' => ['nullable', 'date', 'after_or_equal:startsAt'],
            'isActive' => ['boolean'],
            'conditions' => ['nullable', 'array'],
            'isStackable' => ['boolean'],
            'isFreeShipping' => ['boolean'],
        ]);

        Coupon::create([
            'code' => strtoupper($this->code),
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'value' => $this->value,
            'max_discount_amount' => $this->maxDiscountAmount,
            'min_order_amount' => $this->minOrderAmount,
            'usage_limit' => $this->usageLimit,
            'usage_limit_per_user' => $this->usageLimitPerUser,
            'user_ids' => $this->userIds,
            'starts_at' => $this->startsAt,
            'expires_at' => $this->expiresAt,
            'is_active' => $this->isActive,
            'conditions' => $this->conditions,
            'is_stackable' => $this->isStackable,
            'is_free_shipping' => $this->isFreeShipping,
        ]);

        $this->showCreateModal = false;
        $this->resetForm();
        session()->flash('coupon_message', 'Coupon created successfully.');
    }

    public function edit(int $id): void
    {
        $coupon = Coupon::findOrFail($id);

        $this->editingId = $coupon->id;
        $this->code = $coupon->code;
        $this->name = $coupon->name ?? '';
        $this->description = $coupon->description ?? '';
        $this->type = $coupon->type;
        $this->value = $coupon->value;
        $this->maxDiscountAmount = $coupon->max_discount_amount;
        $this->minOrderAmount = $coupon->min_order_amount;
        $this->usageLimit = $coupon->usage_limit;
        $this->usageLimitPerUser = $coupon->usage_limit_per_user;
        $this->userIds = $coupon->user_ids ?? [];
        $this->startsAt = $coupon->starts_at?->format('Y-m-d\TH:i');
        $this->expiresAt = $coupon->expires_at?->format('Y-m-d\TH:i');
        $this->isActive = (bool) $coupon->is_active;
        $this->conditions = $coupon->conditions ?? [];
        $this->isStackable = (bool) $coupon->is_stackable;
        $this->isFreeShipping = (bool) $coupon->is_free_shipping;

        $this->showEditModal = true;
    }

    public function update(): void
    {
        if ($this->editingId === null) {
            return;
        }

        $this->validate([
            'code' => ['required', 'string', 'max:50', 'unique:coupons,code,'.$this->editingId],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:percentage,fixed,buy_x_get_y,tiered,free_shipping'],
            'value' => ['required', 'integer', 'min:0'],
            'maxDiscountAmount' => ['nullable', 'integer', 'min:0'],
            'minOrderAmount' => ['nullable', 'integer', 'min:0'],
            'usageLimit' => ['nullable', 'integer', 'min:1'],
            'usageLimitPerUser' => ['nullable', 'integer', 'min:1'],
            'userIds' => ['nullable', 'array'],
            'startsAt' => ['nullable', 'date'],
            'expiresAt' => ['nullable', 'date', 'after_or_equal:startsAt'],
            'isActive' => ['boolean'],
            'conditions' => ['nullable', 'array'],
            'isStackable' => ['boolean'],
            'isFreeShipping' => ['boolean'],
        ]);

        $coupon = Coupon::findOrFail($this->editingId);

        $coupon->update([
            'code' => strtoupper($this->code),
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'value' => $this->value,
            'max_discount_amount' => $this->maxDiscountAmount,
            'min_order_amount' => $this->minOrderAmount,
            'usage_limit' => $this->usageLimit,
            'usage_limit_per_user' => $this->usageLimitPerUser,
            'user_ids' => $this->userIds,
            'starts_at' => $this->startsAt,
            'expires_at' => $this->expiresAt,
            'is_active' => $this->isActive,
            'conditions' => $this->conditions,
            'is_stackable' => $this->isStackable,
            'is_free_shipping' => $this->isFreeShipping,
        ]);

        $this->showEditModal = false;
        $this->resetForm();
        session()->flash('coupon_message', 'Coupon updated successfully.');
    }

    public function delete(int $id): void
    {
        $coupon = Coupon::findOrFail($id);
        $coupon->delete();
        session()->flash('coupon_message', 'Coupon deleted successfully.');
    }

    public function closeModals(): void
    {
        $this->showCreateModal = false;
        $this->showEditModal = false;
        $this->resetForm();
    }

    public function render(): View
    {
        return view('payment-gateway::livewire.manage-coupons', [
            'coupons' => Coupon::latest()->paginate(15),
        ]);
    }
}
