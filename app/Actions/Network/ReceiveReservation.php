<?php

declare(strict_types=1);

namespace App\Actions\Network;

use App\Actions\Inventory\InventoryLedger;
use App\Actions\Inventory\UnitLifecycle;
use App\Enums\AdjustmentReason;
use App\Enums\MovementType;
use App\Enums\ReservationStatus;
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

final class ReceiveReservation
{
    public function __construct(private readonly InventoryLedger $inventoryLedger) {}

    /**
     * Confirm that a dispatched device arrived at the requesting branch.
     *
     * The device moves to this branch's record and becomes available. If the reservation has
     * not expired it keeps holding the device for a linked checkout here; if it expired in
     * transit it is closed and the device is released for ordinary sale. Confirming a received
     * reservation returns it without writes.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $actor, Store $requestingStore, int $reservationId): NetworkReservation
    {
        Gate::forUser($actor)->authorize('requestReservations', $requestingStore);

        $reservation = NetworkReservation::query()->where('requesting_store_id', $requestingStore->id)->findOrFail($reservationId);

        return ReservationLocks::run($reservation, function (NetworkReservation $reservation, ProductDefinition $definition, InventoryUnit $unit, ?NetworkListing $listing, Collection $open) use ($actor, $requestingStore): NetworkReservation {
            if ($reservation->received_at !== null) {
                return $reservation;
            }

            if (! $reservation->isInTransit() || $unit->status !== UnitStatus::IN_TRANSIT) {
                throw ValidationException::withMessages(['reservation' => __('network.reservation.errors.not_in_transit')]);
            }

            $now = now();
            UnitLifecycle::advance($unit, ['status' => UnitStatus::AVAILABLE, 'store_id' => $requestingStore->id]);

            $this->inventoryLedger->apply($actor, $requestingStore, $definition, "reservation:{$reservation->id}:receive", ReservationLocks::fingerprint($reservation, 'receive'), [
                'type' => MovementType::TRANSFER_IN,
                'reason' => AdjustmentReason::TRANSFER,
                'inventory_unit_id' => $unit->id,
                'quantity_change' => 1,
                'unit_status_before' => UnitStatus::IN_TRANSIT,
                'unit_status_after' => UnitStatus::AVAILABLE,
                'reference_type' => NetworkReservation::MORPH_ALIAS,
                'reference_id' => $reservation->id,
                'occurred_at' => $now,
            ]);

            $released = $reservation->isExpiredAt($now);
            $reservation->update([
                'received_by' => $actor->id,
                'received_at' => $now,
                ...($released ? ['status' => ReservationStatus::EXPIRED, 'active_unit_id' => null, 'expired_at' => $reservation->expires_at] : []),
            ]);

            ReservationLocks::audit($reservation, $actor, $requestingStore->id, 'reservation_received', "received by {$requestingStore->code}", ['released' => $released]);

            return $reservation;
        });
    }
}
