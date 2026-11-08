<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Models\Store;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CompleteInventorySetup
{
    /**
     * Close a branch's initial inventory setup. From then on, opening balances and opening
     * device registration are refused; new supplier stock enters through purchase receiving.
     *
     * Completing an already completed branch returns it unchanged.
     *
     * @throws AuthorizationException
     */
    public function __invoke(User $actor, Store $store): Store
    {
        Gate::forUser($actor)->authorize('adjustInventory', $store);

        return DB::transaction(function () use ($actor, $store): Store {
            $locked = Store::query()->whereKey($store->id)->lockForUpdate()->firstOrFail();

            if ($locked->inventorySetupIsOpen()) {
                $locked->forceFill([
                    'inventory_setup_completed_at' => now(),
                    'inventory_setup_completed_by' => $actor->id,
                ])->save();
            }

            return $locked;
        });
    }
}
