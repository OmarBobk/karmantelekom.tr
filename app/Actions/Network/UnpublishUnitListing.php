<?php

declare(strict_types=1);

namespace App\Actions\Network;

use App\Models\NetworkListing;
use App\Models\Store;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class UnpublishUnitListing
{
    /**
     * Withdraw one of the branch's devices from Store Network, whatever its stock state.
     *
     * Withdrawing a device that is not listed changes nothing and logs nothing.
     *
     * @throws AuthorizationException
     */
    public function __invoke(User $actor, Store $store, int $unitId): ?NetworkListing
    {
        Gate::forUser($actor)->authorize('shareListings', $store);

        $unit = $store->units()->whereKey($unitId)->firstOrFail();

        return DB::transaction(function () use ($actor, $store, $unit): ?NetworkListing {
            $listing = NetworkListing::query()->where('inventory_unit_id', $unit->id)->lockForUpdate()->first();

            if ($listing === null || ! $listing->isPublished()) {
                return $listing;
            }

            $listing->update(['unpublished_by' => $actor->id, 'unpublished_at' => now()]);

            activity('store_network')
                ->performedOn($listing)
                ->causedBy($actor)
                ->event('listing_unpublished')
                ->withProperties(['store_id' => $store->id, 'inventory_unit_id' => $unit->id])
                ->log("Unit #{$unit->id} withdrawn from Store Network by {$store->code}");

            return $listing;
        });
    }
}
