<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Database\Eloquent\Model;
use Kreetancraft\PaymentGateway\Models\Payment;

class PaymentPolicy
{
    use HandlesAuthorization;

    public function viewAny($user): bool
    {
        return $user->can('view-payments');
    }

    public function view($user, Payment $payment): bool
    {
        return $user->can('view-payments');
    }

    public function create($user): bool
    {
        return $user->can('create-payments');
    }

    public function update($user, Payment $payment): bool
    {
        return $user->can('edit-payments');
    }

    public function refund($user, Payment $payment): bool
    {
        return $user->can('refund-payments');
    }

    public function delete($user, Payment $payment): bool
    {
        return $user->can('delete-payments');
    }
}
