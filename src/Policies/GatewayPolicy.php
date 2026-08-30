<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Kreetancraft\PaymentGateway\Models\Gateway;

class GatewayPolicy extends PaymentGatewayPolicy
{
    public const PERMISSION_SUBJECT = 'gateway';

    public const PERMISSION_EXTRA_METHODS = ['toggle'];

    public function viewAny(Authenticatable $user): bool
    {
        return $this->allows($user, 'view');
    }

    public function view(Authenticatable $user, ?Gateway $gateway = null): bool
    {
        return $this->allows($user, 'view');
    }

    public function update(Authenticatable $user, ?Gateway $gateway = null): bool
    {
        return $this->allows($user, 'update');
    }

    public function toggle(Authenticatable $user, ?Gateway $gateway = null): bool
    {
        return $this->allows($user, 'toggle');
    }
}
