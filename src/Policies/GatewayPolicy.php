<?php

namespace Kreetancraft\PaymentGateway\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Kreetancraft\PaymentGateway\Models\Gateway;

class GatewayPolicy extends PaymentGatewayPolicy
{
    public const PERMISSION_SUBJECT = 'gateway';

    public const PERMISSION_EXTRA_METHODS = ['toggle'];

    public function viewAny(?Authenticatable $user = null): bool
    {
        return $this->allows($user, 'view');
    }

    public function view(?Authenticatable $user = null, ?Gateway $gateway = null): bool
    {
        return $this->allows($user, 'view');
    }

    public function create(?Authenticatable $user = null): bool
    {
        return $this->allows($user, 'create');
    }

    public function update(?Authenticatable $user = null, ?Gateway $gateway = null): bool
    {
        return $this->allows($user, 'update');
    }

    public function delete(?Authenticatable $user = null, ?Gateway $gateway = null): bool
    {
        return $this->allows($user, 'delete');
    }

    public function toggle(?Authenticatable $user = null, ?Gateway $gateway = null): bool
    {
        return $this->allows($user, 'toggle');
    }
}
