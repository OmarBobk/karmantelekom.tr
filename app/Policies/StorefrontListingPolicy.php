<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Database\Seeders\OperationalStoreSetupSeeder;

/**
 * The storefront publisher workspace. Publishing is its own permission, held by the owner role
 * and grantable to another user; it never includes pricing, which stays with the product admin.
 */
class StorefrontListingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo(OperationalStoreSetupSeeder::PUBLISH_STOREFRONT_PERMISSION);
    }

    /**
     * Link, create, publish, withdraw, and unlink storefront listings.
     */
    public function publish(User $user): bool
    {
        return $user->checkPermissionTo(OperationalStoreSetupSeeder::PUBLISH_STOREFRONT_PERMISSION);
    }
}
