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
     * Determine whether the user can sell at this store's counter.
     *
     * The owner holds use_pos through the admin role; an employee needs a direct grant and a
     * current assignment to this store. Both are re-queried on every check.
     */
    public function usePos(User $user, Store $store): bool
    {
        return $user->checkPermissionTo('use_pos') && $store->isAccessibleBy($user);
    }

    /**
     * Determine whether the user can see every cashier's sales in this store (owner only).
     */
    public function viewAllSales(User $user, Store $store): bool
    {
        return $this->ownerHas($user, 'use_pos') && $store->isAccessibleBy($user);
    }

    /**
     * Determine whether the user can change a line price at checkout (owner only, with a reason).
     */
    public function adjustSalePrices(User $user, Store $store): bool
    {
        return $this->ownerHas($user, 'use_pos') && $store->isAccessibleBy($user);
    }

    /**
     * Determine whether an employee can discount counter lines in this store.
     *
     * Needs counter access to the store, the direct discount permission, and an owner-set
     * maximum percentage; without the percentage the permission alone allows nothing. The
     * owner discounts through adjustSalePrices instead.
     */
    public function giveDiscounts(User $user, Store $store): bool
    {
        return ! $user->hasRole('admin')
            && $this->usePos($user, $store)
            && $user->checkPermissionTo('give_pos_discounts')
            && $user->posDiscountGrant()->exists();
    }

    /**
     * Determine whether the user can leave part or all of a customer's sale unpaid at this
     * store's counter (owner only).
     */
    public function sellOnCredit(User $user, Store $store): bool
    {
        return $this->ownerHas($user, 'sell_on_credit') && $this->usePos($user, $store);
    }

    /**
     * Determine whether the user can record the resolution of a voided terminal approval (owner only).
     */
    public function resolveCheckoutAttempts(User $user, Store $store): bool
    {
        return $this->ownerHas($user, 'use_pos') && $store->isAccessibleBy($user);
    }

    /**
     * Determine whether the user can set a used device's individual selling price in this store.
     */
    public function setUnitSellingPrices(User $user, Store $store): bool
    {
        return $this->ownerHas($user, 'set_selling_prices') && $store->isAccessibleBy($user);
    }

    /**
     * Determine whether the user can grant or revoke counter access and discount limits for employees.
     */
    public function managePosAccess(User $user): bool
    {
        return $this->canManageStores($user);
    }

    /**
     * Determine whether the user can add or deactivate this store's card terminals.
     */
    public function manageCardTerminals(User $user, Store $store): bool
    {
        return $this->canManageStores($user);
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
