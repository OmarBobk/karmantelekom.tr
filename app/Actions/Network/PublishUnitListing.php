<?php

declare(strict_types=1);

namespace App\Actions\Network;

use App\Models\InventoryUnit;
use App\Models\NetworkListing;
use App\Models\ProductDefinition;
use App\Models\Store;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class PublishUnitListing
{
    /**
     * Share one of the branch's devices on Store Network, from the listing revision the operator saw.
     *
     * Runs under the inventory lock order (definition, unit, then listing), so it serializes with
     * checkout and price changes. A device this branch already shares is returned unchanged with
     * no audit event, which makes retries harmless. Otherwise the listing must still be at the
     * expected revision, or the request is stale. No stock movement is written.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $actor, Store $store, int $unitId, int $expectedRevision): NetworkListing
    {
        Gate::forUser($actor)->authorize('shareListings', $store);

        return ListingLocks::run($store, $unitId, function (ProductDefinition $definition, InventoryUnit $unit, ?NetworkListing $listing) use ($actor, $store, $expectedRevision): NetworkListing {
            if ($listing?->isPublishedBy($store)) {
                return $listing;
            }

            ListingLocks::assertCurrent($listing, $expectedRevision);

            $problem = ListingEligibility::problem($definition, $unit);

            if ($problem !== null) {
                throw ValidationException::withMessages(['listing' => $problem]);
            }

            $attributes = [
                'store_id' => $store->id,
                'published_by' => $actor->id,
                'published_at' => now(),
                'unpublished_by' => null,
                'unpublished_at' => null,
            ];

            if ($listing === null) {
                $listing = NetworkListing::query()->create(['inventory_unit_id' => $unit->id, 'revision' => 1, ...$attributes]);
            } else {
                $listing->update([...$attributes, 'revision' => $listing->revision + 1]);
            }

            activity('store_network')
                ->performedOn($listing)
                ->causedBy($actor)
                ->event('listing_published')
                ->withProperties(['store_id' => $store->id, 'inventory_unit_id' => $unit->id, 'revision' => $listing->revision])
                ->log("Unit #{$unit->id} published to Store Network by {$store->code}");

            return $listing;
        });
    }
}
