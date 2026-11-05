<?php

declare(strict_types=1);

namespace App\Policies\Concerns;

use App\Models\User;

trait RequiresOwnerPermission
{
    /**
     * Operational management is owner-only: the admin role and the feature permission are both required.
     */
    private function ownerHas(User $user, string $permission): bool
    {
        return $user->hasRole('admin') && $user->checkPermissionTo($permission);
    }
}
