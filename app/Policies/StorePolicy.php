<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Store;
use App\Models\User;
use App\Policies\Concerns\RequiresOwnerPermission;
use Illuminate\Auth\Access\HandlesAuthorization;

class StorePolicy
{
    use HandlesAuthorization;
    use RequiresOwnerPermission;

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
     * Determine whether the user can open the inventory area (store picker).
     */
    public function viewAnyInventory(User $user): bool
    {
        return $this->ownerHas($user, 'view_inventory');
    }

    /**
     * Determine whether the user can view this store's inventory, units, and movement history.
     *
     * Requires the feature permission and store access; neither implies the other.
     */
    public function viewInventory(User $user, Store $store): bool
    {
        return $this->ownerHas($user, 'view_inventory') && $store->isAccessibleBy($user);
    }

    /**
     * Determine whether the user can record opening stock, adjustments, and unit changes in this store.
     */
    public function adjustInventory(User $user, Store $store): bool
    {
        return $this->ownerHas($user, 'adjust_inventory') && $store->isAccessibleBy($user);
    }

    /**
     * Determine whether the user can inspect used devices and open or close their repairs in this store.
     */
    public function serviceDevices(User $user, Store $store): bool
    {
        return $this->ownerHas($user, 'service_devices') && $store->isAccessibleBy($user);
    }

    /**
     * Determine whether the user can see device cost amounts and record or correct device costs in this store.
     */
    public function manageDeviceCosts(User $user, Store $store): bool
    {
        return $this->ownerHas($user, 'manage_device_costs') && $store->isAccessibleBy($user);
    }

    /**
     * Determine whether the user can correct a mistyped IMEI or serial number in this store.
     */
    public function correctUnitIdentity(User $user, Store $store): bool
    {
        return $this->ownerHas($user, 'correct_unit_identity') && $store->isAccessibleBy($user);
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
