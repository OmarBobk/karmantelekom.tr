<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CatalogCategory;
use App\Models\User;
use App\Policies\Concerns\RequiresOwnerPermission;

class CatalogCategoryPolicy
{
    use RequiresOwnerPermission;

    public function viewAny(User $user): bool
    {
        return $this->ownerHas($user, 'manage_catalog');
    }

    public function create(User $user): bool
    {
        return $this->ownerHas($user, 'manage_catalog');
    }

    public function update(User $user, CatalogCategory $catalogCategory): bool
    {
        return $this->ownerHas($user, 'manage_catalog');
    }

    public function delete(User $user, CatalogCategory $catalogCategory): bool
    {
        return false;
    }
}
