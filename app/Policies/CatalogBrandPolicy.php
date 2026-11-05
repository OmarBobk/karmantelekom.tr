<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CatalogBrand;
use App\Models\User;
use App\Policies\Concerns\RequiresOwnerPermission;

class CatalogBrandPolicy
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

    public function update(User $user, CatalogBrand $catalogBrand): bool
    {
        return $this->ownerHas($user, 'manage_catalog');
    }

    public function delete(User $user, CatalogBrand $catalogBrand): bool
    {
        return false;
    }
}
