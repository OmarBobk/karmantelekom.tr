<?php

declare(strict_types=1);

namespace App\Actions\Network;

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

final class RejectReservation
{
    /**
     * Decline another branch's pending request, with a reason. Rejecting a rejected request
     * returns it without writes.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $actor, Store $owningStore, int $reservationId, ?string $reason): NetworkReservation
    {
        Gate::forUser($actor)->authorize('manageReservations', $owningStore);

        $reservation = NetworkReservation::query()->where('owning_store_id', $owningStore->id)->findOrFail($reservationId);
        $reason = ReservationLocks::note($reason, 'decision_note', required: true);

        return ReservationLocks::run($reservation, function (NetworkReservation $reservation, ProductDefinition $definition, InventoryUnit $unit, ?NetworkListing $listing, Collection $open) use ($actor, $owningStore, $reason): NetworkReservation {
            if ($reservation->status === ReservationStatus::REJECTED) {
                return $reservation;
            }

            if ($reservation->status !== ReservationStatus::PENDING) {
                throw ValidationException::withMessages(['reservation' => __('network.reservation.errors.not_pending', ['status' => $reservation->status->label()])]);
            }

            $reservation->update([
                'status' => ReservationStatus::REJECTED,
                'decided_by' => $actor->id,
                'decided_at' => now(),
                'decision_note' => $reason,
            ]);

            ReservationLocks::audit($reservation, $actor, $owningStore->id, 'reservation_rejected', "rejected by {$owningStore->code}", ['reason' => $reason]);

            return $reservation;
        });
    }
}
