<?php

declare(strict_types=1);

namespace App\Actions\Storefront;

use App\Actions\Network\ListingLocks;
use App\Models\InventoryUnit;
use App\Models\NetworkListing;
use App\Models\ProductDefinition;
use App\Models\Store;
use App\Models\StorefrontAllocation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class SetStorefrontDevice
{
    /**
     * Make one of the branch's devices eligible for the storefront, or withdraw it.
     *
     * Runs under the inventory lock order (definition, device, then its listing rows), so it
     * serializes with checkout. Independent of Store Network sharing. Eligibility is checked only
     * when enabling; the current state is returned unchanged without an audit event.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $actor, Store $store, int $unitId, bool $eligible): ?StorefrontAllocation
    {
        Gate::forUser($actor)->authorize('allocateStorefrontStock', $store);

        return ListingLocks::run($store, $unitId, function (ProductDefinition $definition, InventoryUnit $unit, ?NetworkListing $listing) use ($actor, $store, $eligible): ?StorefrontAllocation {
            $allocation = StorefrontAllocation::query()->where('inventory_unit_id', $unit->id)->lockForUpdate()->first();
            $target = $eligible ? 1 : 0;
            $current = $allocation !== null && $allocation->store_id === $store->id ? $allocation->quantity : 0;

            if ($current === $target) {
                return $allocation;
            }

            if ($eligible) {
                $problem = StorefrontEligibility::deviceProblem($definition, $unit);

                if ($problem !== null) {
                    throw ValidationException::withMessages(['allocation' => $problem]);
                }
            }

            $attributes = ['store_id' => $store->id, 'product_definition_id' => $definition->id, 'quantity' => $target, 'updated_by' => $actor->id];

            if ($allocation === null) {
                $allocation = StorefrontAllocation::query()->create(['inventory_unit_id' => $unit->id, ...$attributes]);
            } else {
                $allocation->update($attributes);
            }

            activity('storefront')
                ->performedOn($allocation)
                ->causedBy($actor)
                ->event($eligible ? 'device_allocated' : 'device_withdrawn')
                ->withProperties(['store_id' => $store->id, 'inventory_unit_id' => $unit->id])
                ->log("Unit #{$unit->id} ".($eligible ? 'made eligible for' : 'withdrawn from')." the storefront by {$store->code}");

            return $allocation;
        });
    }
}
