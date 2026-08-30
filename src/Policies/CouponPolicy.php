<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Kreetancraft\PaymentGateway\Models\Coupon;

class CouponPolicy extends PaymentGatewayPolicy
{
    public const PERMISSION_SUBJECT = 'coupon';

    public function viewAny(Authenticatable $user): bool
    {
        return $this->allows($user, 'view');
    }

    public function view(Authenticatable $user, ?Coupon $coupon = null): bool
    {
        return $this->allows($user, 'view');
    }

    public function create(Authenticatable $user): bool
    {
        return $this->allows($user, 'create');
    }

    public function update(Authenticatable $user, ?Coupon $coupon = null): bool
    {
        return $this->allows($user, 'update');
    }

    public function delete(Authenticatable $user, ?Coupon $coupon = null): bool
    {
        return $this->allows($user, 'delete');
    }
}
