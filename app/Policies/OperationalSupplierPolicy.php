<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\OperationalSupplier;
use App\Models\User;
use App\Policies\Concerns\RequiresOwnerPermission;

class OperationalSupplierPolicy
{
    use RequiresOwnerPermission;

    public function viewAny(User $user): bool
    {
        return $this->ownerHas($user, 'manage_suppliers');
    }

    public function create(User $user): bool
    {
        return $this->ownerHas($user, 'manage_suppliers');
    }

    public function update(User $user, OperationalSupplier $operationalSupplier): bool
    {
        return $this->ownerHas($user, 'manage_suppliers');
    }

    /**
     * View the supplier's balances and ledger. Amounts are limited to stores the user can access.
     */
    public function viewAccount(User $user, OperationalSupplier $operationalSupplier): bool
    {
        return $this->viewAccounts($user);
    }

    /**
     * View supplier balances across suppliers, limited to stores the user can access.
     */
    public function viewAccounts(User $user): bool
    {
        return $this->ownerHas($user, 'manage_purchases') || $this->ownerHas($user, 'pay_suppliers');
    }

    public function delete(User $user, OperationalSupplier $operationalSupplier): bool
    {
        return false;
    }
}
