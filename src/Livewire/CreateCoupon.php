<?php

namespace Kreetancraft\PaymentGateway\Livewire;

use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Kreetancraft\PaymentGateway\Layout;
use Kreetancraft\PaymentGateway\Models\Coupon;
use Livewire\Attributes\Title;
use Livewire\Component;

class CreateCoupon extends Component
{
    public string $template = '';

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

    public function applyTemplate(string $selected): void
    {
        switch ($selected) {
            case 'percent_10':
                $this->type = 'percentage';
                $this->value = 10;
                $this->name = '10% Off Discount';
                $this->isFreeShipping = false;
                break;
            case 'percent_20':
                $this->type = 'percentage';
                $this->value = 20;
                $this->name = '20% Off Discount';
                $this->isFreeShipping = false;
                break;
            case 'fixed_20':
                $this->type = 'fixed';
                $this->value = 2000; // $20.00
                $this->name = '$20 Off Order';
                $this->isFreeShipping = false;
                break;
            case 'free_shipping':
                $this->type = 'free_shipping';
                $this->value = 0;
                $this->name = 'Free Shipping Promo';
                $this->isFreeShipping = true;
                break;
        }
    }

    public function save(): void
    {
        $this->authorize('create', Coupon::class);

        $this->validate([
            'code' => ['required', 'string', 'max:50', 'unique:coupons,code'],
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

        Coupon::create([
            'uuid' => (string) Str::uuid(),
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
            'starts_at' => $this->startsAt,
            'expires_at' => $this->expiresAt,
            'is_active' => $this->isActive,
            'is_stackable' => $this->isStackable,
            'is_free_shipping' => $this->isFreeShipping || $this->type === 'free_shipping',
        ]);

        if (class_exists(Flux::class) && app()->bound('flux')) {
            Flux::toast(variant: 'success', text: __('Coupon [:code] created successfully.', ['code' => $this->code]));
        }

        $this->redirect(route(config('payment-gateway.routes.names.coupons', 'admin.payment.coupons')), navigate: true);
    }

    #[Title('Create Coupon - Admin')]
    public function render(): View
    {
        return view('payment-gateway::livewire.create-coupon')->layout(Layout::admin());
    }
}
