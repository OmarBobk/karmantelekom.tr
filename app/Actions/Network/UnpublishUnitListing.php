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

final class UnpublishUnitListing
{
    /**
     * Withdraw one of the branch's devices from Store Network, from the listing revision the operator saw.
     *
     * Device and listing ownership are rechecked under the same locks as publishing. A device that
     * is not shared is returned unchanged with no audit event. Otherwise the listing must still be
     * at the expected revision, so an old stop-sharing action cannot withdraw a newer publication.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $actor, Store $store, int $unitId, int $expectedRevision): ?NetworkListing
    {
        Gate::forUser($actor)->authorize('shareListings', $store);

        return ListingLocks::run($store, $unitId, function (ProductDefinition $definition, InventoryUnit $unit, ?NetworkListing $listing) use ($actor, $store, $expectedRevision): ?NetworkListing {
            if ($listing === null || ! $listing->isPublished()) {
                return $listing;
            }

            if (! $listing->isPublishedBy($store)) {
                throw ValidationException::withMessages(['listing' => __('network.errors.other_branch_listing')]);
            }

            ListingLocks::assertCurrent($listing, $expectedRevision);

            $listing->update(['unpublished_by' => $actor->id, 'unpublished_at' => now(), 'revision' => $listing->revision + 1]);

            activity('store_network')
                ->performedOn($listing)
                ->causedBy($actor)
                ->event('listing_unpublished')
                ->withProperties(['store_id' => $store->id, 'inventory_unit_id' => $unit->id, 'revision' => $listing->revision])
                ->log("Unit #{$unit->id} withdrawn from Store Network by {$store->code}");

            return $listing;
        });
    }
}
