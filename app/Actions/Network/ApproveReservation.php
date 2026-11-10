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

final class ApproveReservation
{
    /**
     * Approve another branch's pending request for one of this branch's devices.
     *
     * Under the device locks the request is checked again: the listing is still the revision the
     * requester saw, the branch still holds the device and it is still sellable, no other
     * reservation is active, no storefront order holds it, and no checkout holding the device
     * has started payment. The hold
     * starts now and lasts the branch's configured reservation time. Approving an approved
     * request returns it without writes.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $actor, Store $owningStore, int $reservationId): NetworkReservation
    {
        Gate::forUser($actor)->authorize('manageReservations', $owningStore);

        $reservation = NetworkReservation::query()->where('owning_store_id', $owningStore->id)->findOrFail($reservationId);
        $minutes = $owningStore->reservation_minutes;

        return ReservationLocks::run($reservation, function (NetworkReservation $reservation, ProductDefinition $definition, InventoryUnit $unit, ?NetworkListing $listing, Collection $open) use ($actor, $owningStore, $minutes): NetworkReservation {
            if ($reservation->status === ReservationStatus::APPROVED) {
                return $reservation;
            }

            if ($reservation->status !== ReservationStatus::PENDING) {
                throw ValidationException::withMessages(['reservation' => __('network.reservation.errors.not_pending', ['status' => $reservation->status->label()])]);
            }

            if ($minutes === null) {
                throw ValidationException::withMessages(['reservation' => __('network.reservation.errors.hold_not_set')]);
            }

            if (ReservationLocks::paymentStartedFor($unit)) {
                throw ValidationException::withMessages(['reservation' => __('network.reservation.errors.payment_started')]);
            }

            if ($listing === null || ! $listing->isPublishedBy($owningStore) || $listing->revision !== $reservation->listing_revision) {
                throw ValidationException::withMessages(['reservation' => __('network.reservation.errors.listing_changed_since_request')]);
            }

            $problem = $unit->store_id !== $owningStore->id ? __('network.reservation.errors.unavailable') : ListingEligibility::problem($definition, $unit);

            if ($problem !== null) {
                throw ValidationException::withMessages(['reservation' => $problem]);
            }

            if ($open->contains(fn (NetworkReservation $other): bool => $other->id !== $reservation->id && $other->blocksSale())) {
                throw ValidationException::withMessages(['reservation' => __('network.reservation.errors.already_reserved')]);
            }

            if (BranchOrderHolds::unitHolds([$unit->id], lock: true)->isNotEmpty()) {
                throw ValidationException::withMessages(['reservation' => __('storefront.orders.errors.device_held')]);
            }

            ReservationLocks::closeExpired($open);

            $now = now();
            $reservation->update([
                'status' => ReservationStatus::APPROVED,
                'active_unit_id' => $unit->id,
                'decided_by' => $actor->id,
                'decided_at' => $now,
                'expires_at' => $now->copy()->addMinutes($minutes),
            ]);

            ReservationLocks::audit($reservation, $actor, $owningStore->id, 'reservation_approved', "approved by {$owningStore->code}", ['expires_at' => $reservation->expires_at->toIso8601String(), 'hold_minutes' => $minutes]);

            return $reservation;
        });
    }
}
