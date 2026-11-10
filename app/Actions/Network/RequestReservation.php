<?php

declare(strict_types=1);

namespace App\Actions\Network;

use App\Actions\Storefront\BranchOrderHolds;
use App\Enums\ReservationStatus;
use App\Models\InventoryUnit;
use App\Models\NetworkListing;
use App\Models\NetworkReservation;
use App\Models\ProductDefinition;
use App\Models\Store;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class RequestReservation
{
    /**
     * Ask the branch that shared a device to hold it for this branch.
     *
     * The request is bound to the listing revision the employee saw, and leaves the device
     * available until the owning branch approves. Repeating a request this branch already has
     * open returns it without writes.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $actor, Store $requestingStore, int $listingId, int $expectedRevision, ?string $note = null): NetworkReservation
    {
        Gate::forUser($actor)->authorize('requestReservations', $requestingStore);

        $note = ReservationLocks::note($note, 'request_note', required: false);
        $unitId = NetworkListing::query()->whereKey($listingId)->value('inventory_unit_id')
            ?? throw ValidationException::withMessages(['listing' => __('network.reservation.errors.listing_changed')]);

        return ReservationLocks::forUnit($unitId, function (ProductDefinition $definition, InventoryUnit $unit, ?NetworkListing $listing, Collection $open) use ($actor, $requestingStore, $expectedRevision, $note): NetworkReservation {
            $mine = $open->first(fn (NetworkReservation $reservation): bool => $reservation->requesting_store_id === $requestingStore->id
                && ($reservation->status === ReservationStatus::PENDING || $reservation->blocksSale()));

            if ($mine !== null) {
                return $mine;
            }

            if ($listing === null || ! $listing->isPublished() || $listing->revision !== $expectedRevision || $listing->store_id !== $unit->store_id) {
                throw ValidationException::withMessages(['listing' => __('network.reservation.errors.listing_changed')]);
            }

            if ($listing->store_id === $requestingStore->id) {
                throw ValidationException::withMessages(['listing' => __('network.reservation.errors.own_device')]);
            }

            if (ListingEligibility::problem($definition, $unit) !== null || $open->contains(fn (NetworkReservation $reservation): bool => $reservation->blocksSale())
                || BranchOrderHolds::unitHolds([$unit->id], lock: true)->isNotEmpty()) {
                throw ValidationException::withMessages(['listing' => __('network.reservation.errors.unavailable')]);
            }

            $reservation = NetworkReservation::query()->create([
                'network_listing_id' => $listing->id,
                'listing_revision' => $listing->revision,
                'inventory_unit_id' => $unit->id,
                'product_definition_id' => $definition->id,
                'owning_store_id' => $listing->store_id,
                'requesting_store_id' => $requestingStore->id,
                'status' => ReservationStatus::PENDING,
                'requested_by' => $actor->id,
                'requested_at' => now(),
                'request_note' => $note,
            ]);

            ReservationLocks::audit($reservation, $actor, $requestingStore->id, 'reservation_requested', "requested by {$requestingStore->code}", ['listing_revision' => $listing->revision]);

            return $reservation;
        });
    }
}
