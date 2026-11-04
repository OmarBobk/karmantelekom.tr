<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Store;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class StorePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $this->canManageStores($user);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Store $store): bool
    {
        return $this->canManageStores($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->canManageStores($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Store $store): bool
    {
        return $this->canManageStores($user);
    }

    /**
     * Determine whether the user may operate in the store.
     *
     * Grants store membership-level access only, never management or operational permissions.
     */
    public function access(User $user, Store $store): bool
    {
        return $store->isAccessibleBy($user);
    }

    /**
     * Determine whether the user can assign or remove employees for the store.
     */
    public function manageEmployees(User $user, Store $store): bool
    {
        return $this->canManageStores($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Store $store): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Store $store): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Store $store): bool
    {
        return false;
    }

    /**
     * Store management requires both the owner (admin) role and the manage_stores permission.
     */
    private function canManageStores(User $user): bool
    {
        return $user->hasRole('admin') && $user->checkPermissionTo('manage_stores');
    }
}
