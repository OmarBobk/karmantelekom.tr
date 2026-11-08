<?php

declare(strict_types=1);

namespace App\Actions\Network;

use App\Models\NetworkListing;
use App\Models\ProductDefinition;
use App\Models\Store;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class PublishUnitListing
{
    /**
     * Share one of the branch's devices on Store Network.
     *
     * Eligibility is checked under the inventory lock order (definition, then unit), so it
     * serializes with checkout and price changes. Publishing an already published device
     * changes nothing and logs nothing. No stock movement is written.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $actor, Store $store, int $unitId): NetworkListing
    {
        Gate::forUser($actor)->authorize('shareListings', $store);

        $unit = $store->units()->whereKey($unitId)->firstOrFail();

        return DB::transaction(function () use ($actor, $store, $unit): NetworkListing {
            $definition = ProductDefinition::query()->whereKey($unit->product_definition_id)->lockForUpdate()->firstOrFail();
            $locked = $store->units()->whereKey($unit->id)->lockForUpdate()->firstOrFail();

            $problem = ListingEligibility::problem($definition, $locked);

            if ($problem !== null) {
                throw ValidationException::withMessages(['listing' => $problem]);
            }

            $listing = NetworkListing::query()->where('inventory_unit_id', $locked->id)->lockForUpdate()->first();

            if ($listing !== null && $listing->isPublished() && $listing->store_id === $store->id) {
                return $listing;
            }

            $attributes = [
                'store_id' => $store->id,
                'published_by' => $actor->id,
                'published_at' => now(),
                'unpublished_by' => null,
                'unpublished_at' => null,
            ];

            if ($listing === null) {
                $listing = NetworkListing::query()->create(['inventory_unit_id' => $locked->id, ...$attributes]);
            } else {
                $listing->update($attributes);
            }

            activity('store_network')
                ->performedOn($listing)
                ->causedBy($actor)
                ->event('listing_published')
                ->withProperties(['store_id' => $store->id, 'inventory_unit_id' => $locked->id])
                ->log("Unit #{$locked->id} published to Store Network by {$store->code}");

            return $listing;
        });
    }
}
