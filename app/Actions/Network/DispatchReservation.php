<?php

declare(strict_types=1);

namespace App\Actions\Network;

use App\Actions\Inventory\InventoryLedger;
use App\Actions\Inventory\UnitLifecycle;
use App\Enums\AdjustmentReason;
use App\Enums\MovementType;
use App\Enums\UnitStatus;
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

final class DispatchReservation
{
    public function __construct(private readonly InventoryLedger $inventoryLedger) {}

    /**
     * Send a reserved device to the requesting branch.
     *
     * Only an active, undispatched reservation can be dispatched, and only while this branch
     * holds the device available and no checkout holding it has started payment. The device
     * becomes in transit, stays on this branch's record until receipt, and leaves Store Network.
     * Dispatching a dispatched reservation returns it without writes.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $actor, Store $owningStore, int $reservationId): NetworkReservation
    {
        Gate::forUser($actor)->authorize('manageReservations', $owningStore);

        $reservation = NetworkReservation::query()->where('owning_store_id', $owningStore->id)->findOrFail($reservationId);

        return ReservationLocks::run($reservation, function (NetworkReservation $reservation, ProductDefinition $definition, InventoryUnit $unit, ?NetworkListing $listing, Collection $open) use ($actor, $owningStore): NetworkReservation {
            if ($reservation->dispatched_at !== null) {
                return $reservation;
            }

            if (! $reservation->isActive()) {
                throw ValidationException::withMessages(['reservation' => __('network.reservation.errors.not_active', ['status' => __('network.reservation_states.'.$reservation->displayState())])]);
            }

            if (ReservationLocks::paymentStartedFor($unit)) {
                throw ValidationException::withMessages(['reservation' => __('network.reservation.errors.payment_started')]);
            }

            if ($unit->store_id !== $owningStore->id || $unit->status !== UnitStatus::AVAILABLE) {
                throw ValidationException::withMessages(['reservation' => __('network.reservation.errors.device_not_ready', ['status' => $unit->status->label()])]);
            }

            $now = now();
            UnitLifecycle::advance($unit, ['status' => UnitStatus::IN_TRANSIT]);

            $this->inventoryLedger->apply($actor, $owningStore, $definition, "reservation:{$reservation->id}:dispatch", ReservationLocks::fingerprint($reservation, 'dispatch'), [
                'type' => MovementType::TRANSFER_OUT,
                'reason' => AdjustmentReason::TRANSFER,
                'inventory_unit_id' => $unit->id,
                'quantity_change' => -1,
                'unit_status_before' => UnitStatus::AVAILABLE,
                'unit_status_after' => UnitStatus::IN_TRANSIT,
                'reference_type' => NetworkReservation::MORPH_ALIAS,
                'reference_id' => $reservation->id,
                'occurred_at' => $now,
            ]);

            if ($listing?->isPublished()) {
                $listing->update(['unpublished_by' => $actor->id, 'unpublished_at' => $now, 'revision' => $listing->revision + 1]);

                activity('store_network')
                    ->performedOn($listing)
                    ->causedBy($actor)
                    ->event('listing_unpublished')
                    ->withProperties(['store_id' => $owningStore->id, 'inventory_unit_id' => $unit->id, 'revision' => $listing->revision, 'reservation_id' => $reservation->id])
                    ->log("Unit #{$unit->id} withdrawn from Store Network on dispatch by {$owningStore->code}");
            }

            $reservation->update(['dispatched_by' => $actor->id, 'dispatched_at' => $now]);

            ReservationLocks::audit($reservation, $actor, $owningStore->id, 'reservation_dispatched', "dispatched by {$owningStore->code}");

            return $reservation;
        });
    }
}
