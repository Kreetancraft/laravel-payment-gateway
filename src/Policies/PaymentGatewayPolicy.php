<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Throwable;

/**
 * Shared authorization behaviour for payment gateway package policies.
 *
 * This package manages no roles or permissions. Its screens ask Laravel the
 * ordinary question and these policies answer it, so there are two clean ways
 * to control access: install kreetancraft/laravel-user-management, which
 * discovers each policy through Gate::policies() and creates the abilities from
 * the PERMISSION_SUBJECT; or replace a policy outright with Gate::policy() in your own provider.
 *
 * Installed on its own with no permissions anywhere, the screens are open —
 * there is nothing to enforce yet. Enforcement begins the moment permissions exist.
 */
abstract class PaymentGatewayPolicy
{
    use HandlesAuthorization;

    /**
     * The ability name for an action, e.g. `view-payments`.
     */
    public function ability(string $action): string
    {
        $plural = defined(static::class.'::PERMISSION_SUBJECT_PLURAL')
            ? constant(static::class.'::PERMISSION_SUBJECT_PLURAL')
            : Str::plural((string) constant(static::class.'::PERMISSION_SUBJECT'));

        return $action.'-'.Str::kebab((string) $plural);
    }

    protected function allows(?Authenticatable $user, string $action): bool
    {
        if ($user === null) {
            return ! $this->permissionsInUse();
        }

        if (! method_exists($user, 'can')) {
            return true;
        }

        if ($user->can($this->ability($action))) {
            return true;
        }

        return ! $this->permissionsInUse();
    }

    /**
     * Whether this application uses permissions at all.
     */
    private function permissionsInUse(): bool
    {
        if (! class_exists(Permission::class)) {
            return false;
        }

        try {
            return Permission::query()->exists();
        } catch (Throwable) {
            return false;
        }
    }
}
