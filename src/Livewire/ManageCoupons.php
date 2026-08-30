<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use Kreetancraft\PaymentGateway\Models\Coupon;

class ManageCoupons extends Component
{
    public bool $showCreateModal = false;
    public ?string $editingId = null;
    
    public string $newCode = '';
    public string $newName = '';
    public string $newDescription = '';
    public string $newType = 'percentage';
    public int $newValue = 0;
    public ?int $newMaxDiscountAmount = null;
    public ?int $newMinOrderAmount = null;
    public ?int $newUsageLimit = null;
    public ?int $newUsageLimitPerUser = null;
    public array $newUserIds = [];
    public ?string $newStartsAt = null;
    public ?string $newExpiresAt = null;
    public bool $newIsActive = true;
    public ?int $newMaxDiscountAmount = null;
    public ?int $newMinOrderAmount = null;
    public array $newConditions = [];
    public bool $newIsStackable = false;
    public bool $newIsFreeShipping = false;
    public string $newCode = '';
    public string $newLabel = '';
    public string $newIcon = '';
    public string $newCurrencies = '';
    public bool $newIsStackable = false;
    public bool $newIsFreeShipping = false;

    public function create(): void
    {
        $this->resetCreateForm();
        $this->showCreateModal = true;
    }

    public function resetCreateForm(): void
    {
        $this->newCode = '';
        $this->newName = '';
        $this->newDescription = '';
        $this->newType = 'percentage';
        $this->newValue = 0;
        $this->newMaxDiscountAmount = null;
        $this->newMinOrderAmount = null;
        $this->newUsageLimit = null;
        $this->newUsageLimitPerUser = null;
        $this->newUserIds = [];
        $this->newStartsAt = null;
        $this->newExpiresAt = null;
        $this->newIsActive = true;
        $this->newMaxDiscountAmount = null;
        $this->newMinOrderAmount = null;
        $this->newConditions = [];
        $this->newIsStackable = false;
        $this->newIsFreeShipping = false;
        $this->newCode = '';
        $this->newLabel = '';
        $this->newIcon = '';
        $this->newCurrencies = '';
        $this->newIsStackable = false;
        $this->newIsFreeShipping = false;
    }

    public function save(): void
    {
        $this->validate([
            'newCode' => 'required|string|unique:coupons,code|max:50',
            'newLabel' => 'required|string|max:255',
            'newType' => 'required|in:percentage,fixed,buy_x_get_y,tiered,free_shipping',
            'newValue' => 'required|integer|min:0',
            'newMaxDiscountAmount' => 'nullable|integer|min:0',
            'newMinOrderAmount' => 'nullable|integer|min:0',
            'newUsageLimit' => 'nullable|integer|min:1',
            'newUsageLimitPerUser' => 'nullable|integer|min:1',
            'newUserIds' => 'nullable|array',
            'newStartsAt' => 'nullable|date',
            'newExpiresAt' => 'nullable|date|after_or_equal:newStartsAt',
            'newIsActive' => 'boolean',
            'newMaxDiscountAmount' => 'nullable|integer|min:0',
            'newMinOrderAmount' => 'nullable|integer|min:0',
            'newConditions' => 'nullable|array',
            'newIsStackable' => 'boolean',
            'newIsFreeShipping' => 'boolean',
        ]);

        Coupon::create([
            'code' => strtoupper($this->newCode),
            'name' => $this->newName,
            'description' => $this->newDescription,
            'type' => $this->newType,
            'value' => $this->newValue,
            'max_discount_amount' => $this->newMaxDiscountAmount,
            'min_order_amount' => $this->newMinOrderAmount,
            'usage_limit' => $this->newUsageLimit,
            'usage_limit_per_user' => $this->newUsageLimitPerUser,
            'user_ids' => $this->newUserIds,
            'starts_at' => $this->newStartsAt,
            'expires_at' => $this->newExpiresAt,
            'is_active' => $this->newIsActive,
            'max_discount_amount' => $this->newMaxDiscountAmount,
            'min_order_amount' => $this->newMinOrderAmount,
            'conditions' => $this->newConditions,
            'is_stackable' => $this->newIsStackable,
            'is_free_shipping' => $this->newIsFreeShipping,
        ]);

        $this->showCreateModal = false;
        $this->resetCreateForm();
        session()->flash('coupon_message', 'Coupon created successfully.');
    }

    public function edit(int $id): void
    {
        $coupon = Coupon::findOrFail($id);
        
        $this->editingId = $coupon->id;
        $this->newCode = $coupon->code;
        $this->newName = $coupon->name;
        $this->newDescription = $coupon->description;
        $this->newType = $coupon->type;
        $this->newValue = $coupon->value;
        $this->newMaxDiscountAmount = $coupon->max_discount_amount;
        $this->newMinOrderAmount = $coupon->min_order_amount;
        $this->newUsageLimit = $coupon->usage_limit;
        $this->newUsageLimitPerUser = $coupon->usage_limit_per_user;
        $this->newUserIds = $coupon->user_ids ?? [];
        $this->newStartsAt = $coupon->starts_at?->format('Y-m-d\TH:i');
        $this->newExpiresAt = $coupon->expires_at?->format('Y-m-d\TH:i');
        $this->newIsActive = $coupon->is_active;
        $this->newMaxDiscountAmount = $coupon->max_discount_amount;
        $this->newMinOrderAmount = $coupon->min_order_amount;
        $this->newConditions = $coupon->conditions ?? [];
        $this->newIsStackable = $coupon->is_stackable;
        $this->newIsFreeShipping = $coupon->is_free_shipping;
    }

    public function update(): void
    {
        $this->validate([
            'newCode' => 'required|string|unique:coupons,code,' . $this->editingId . '|max:50',
            'newName' => 'required|string|max:255',
            'newType' => 'required|in:percentage,fixed,buy_x_get_y,tiered,free_shipping',
            'newValue' => 'required|integer|min:0',
            'newMaxDiscountAmount' => 'nullable|integer|min:0',
            'newMinOrderAmount' => 'nullable|integer|min:0',
            'newUsageLimit' => 'nullable|integer|min:1',
            'newUsageLimitPerUser' => 'nullable|integer|min:1',
            'newUserIds' => 'nullable|array',
            'newStartsAt' => 'nullable|date',
            'newExpiresAt' => 'nullable|date|after_or_equal:newStartsAt',
            'newIsActive' => 'boolean',
            'newMaxDiscountAmount' => 'nullable|integer|min:0',
            'newMinOrderAmount' => 'nullable|integer|min:0',
            'newConditions' => 'nullable|array',
            'newIsStackable' => 'boolean',
            'newIsFreeShipping' => 'boolean',
        ]);

        $coupon = Coupon::findOrFail($this->editingId);
        $coupon->update([
            'code' => strtoupper($this->newCode),
            'name' => $this->newName,
            'description' => $this->newDescription,
            'type' => $this->newType,
            'value' => $this->newValue,
            'max_discount_amount' => $this->newMaxDiscountAmount,
            'min_order_amount' => $this->newMinOrderAmount,
            'usage_limit' => $this->newUsageLimit,
            'usage_limit_per_user' => $this->newUsageLimitPerUser,
            'user_ids' => $this->newUserIds,
            'starts_at' => $this->newStartsAt,
            'expires_at' => $this->newExpiresAt,
            'is_active' => $this->newIsActive,
            'max_discount_amount' => $this->newMaxDiscountAmount,
            'min_order_amount' => $this->newMinOrderAmount,
            'conditions' => $this->newConditions,
            'is_stackable' => $this->newIsStackable,
            'is_free_shipping' => $this->newIsFreeShipping,
        ]);

        $this->showEditModal = false;
        $this->editingId = null;
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
        $this->editingId = null;
    }

    public function render(): View
    {
        return view('payment-gateway::livewire.manage-coupons', [
            'coupons' => Coupon::latest()->paginate(15),
        ]);
    }
}