<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Kreetancraft\PaymentGateway\Models\Payment;

class PaymentPolicy extends PaymentGatewayPolicy
{
    public const PERMISSION_SUBJECT = 'payment';

    public const PERMISSION_EXTRA_METHODS = ['refund'];

    public function viewAny(Authenticatable $user): bool
    {
        return $this->allows($user, 'view');
    }

    public function view(Authenticatable $user, ?Payment $payment = null): bool
    {
        return $this->allows($user, 'view');
    }

    public function create(Authenticatable $user): bool
    {
        return $this->allows($user, 'create');
    }

    public function update(Authenticatable $user, ?Payment $payment = null): bool
    {
        return $this->allows($user, 'update');
    }

    public function delete(Authenticatable $user, ?Payment $payment = null): bool
    {
        return $this->allows($user, 'delete');
    }

    public function refund(Authenticatable $user, ?Payment $payment = null): bool
    {
        return $this->allows($user, 'refund');
    }
}
