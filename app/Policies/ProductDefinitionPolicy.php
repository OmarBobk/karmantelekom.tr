<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ProductDefinition;
use App\Models\User;
use App\Policies\Concerns\RequiresOwnerPermission;

class ProductDefinitionPolicy
{
    use RequiresOwnerPermission;

    public function viewAny(User $user): bool
    {
        return $this->ownerHas($user, 'manage_catalog');
    }

    public function view(User $user, ProductDefinition $productDefinition): bool
    {
        return $this->ownerHas($user, 'manage_catalog');
    }

    public function create(User $user): bool
    {
        return $this->ownerHas($user, 'manage_catalog');
    }

    public function update(User $user, ProductDefinition $productDefinition): bool
    {
        return $this->ownerHas($user, 'manage_catalog');
    }

    public function delete(User $user, ProductDefinition $productDefinition): bool
    {
        return false;
    }

    public function restore(User $user, ProductDefinition $productDefinition): bool
    {
        return false;
    }

    public function forceDelete(User $user, ProductDefinition $productDefinition): bool
    {
        return false;
    }
}
