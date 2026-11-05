<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Purchase;
use App\Models\Store;
use App\Models\User;
use App\Policies\Concerns\RequiresOwnerPermission;

/**
 * Supplier invoices are owner-only: each action needs its feature permission and access to the invoice's store.
 */
class PurchasePolicy
{
    use RequiresOwnerPermission;

    public const PERMISSIONS = ['manage_purchases', 'receive_purchases', 'pay_suppliers'];

    public function viewAny(User $user): bool
    {
        foreach (self::PERMISSIONS as $permission) {
            if ($this->ownerHas($user, $permission)) {
                return true;
            }
        }

        return false;
    }

    public function view(User $user, Purchase $purchase): bool
    {
        return $this->viewAny($user) && $this->canAccessStore($user, $purchase->store_id);
    }

    public function create(User $user): bool
    {
        return $this->ownerHas($user, 'manage_purchases');
    }

    /**
     * Create a draft, or move one, in a specific store.
     */
    public function createIn(User $user, Store $store): bool
    {
        return $this->ownerHas($user, 'manage_purchases') && $store->isAccessibleBy($user);
    }

    /**
     * Edit, post, cancel, reverse, or copy the invoice.
     */
    public function update(User $user, Purchase $purchase): bool
    {
        return $this->ownerHas($user, 'manage_purchases') && $this->canAccessStore($user, $purchase->store_id);
    }

    public function receive(User $user, Purchase $purchase): bool
    {
        return $this->ownerHas($user, 'receive_purchases') && $this->canAccessStore($user, $purchase->store_id);
    }

    public function pay(User $user, Purchase $purchase): bool
    {
        return $this->ownerHas($user, 'pay_suppliers') && $this->canAccessStore($user, $purchase->store_id);
    }

    public function delete(User $user, Purchase $purchase): bool
    {
        return false;
    }

    private function canAccessStore(User $user, int $storeId): bool
    {
        return Store::query()->accessibleTo($user)->whereKey($storeId)->exists();
    }
}
