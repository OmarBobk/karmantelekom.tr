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

final class CancelReservation
{
    /**
     * Cancel a pending or approved reservation before dispatch, with a reason.
     *
     * The requesting branch's counter staff may cancel their branch's request; the owning
     * branch's reservation managers may cancel a request for their device. Cancellation is
     * refused once a checkout holding the device has started payment. Cancelling a cancelled
     * reservation returns it without writes.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $actor, Store $store, int $reservationId, ?string $reason): NetworkReservation
    {
        $reservation = NetworkReservation::query()
            ->where(fn ($query) => $query->where('requesting_store_id', $store->id)->orWhere('owning_store_id', $store->id))
            ->findOrFail($reservationId);

        Gate::forUser($actor)->authorize($reservation->owning_store_id === $store->id ? 'manageReservations' : 'requestReservations', $store);

        $reason = ReservationLocks::note($reason, 'cancellation_reason', required: true);

        return ReservationLocks::run($reservation, function (NetworkReservation $reservation, ProductDefinition $definition, InventoryUnit $unit, ?NetworkListing $listing, Collection $open) use ($actor, $store, $reason): NetworkReservation {
            if ($reservation->status === ReservationStatus::CANCELLED) {
                return $reservation;
            }

            if (! $reservation->isCancellable()) {
                throw ValidationException::withMessages(['reservation' => __('network.reservation.errors.not_cancellable', ['status' => __('network.reservation_states.'.$reservation->displayState())])]);
            }

            if ($reservation->isApproved() && ReservationLocks::paymentStartedFor($unit)) {
                throw ValidationException::withMessages(['reservation' => __('network.reservation.errors.payment_started')]);
            }

            $reservation->update([
                'status' => ReservationStatus::CANCELLED,
                'active_unit_id' => null,
                'cancelled_by' => $actor->id,
                'cancelled_store_id' => $store->id,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            ReservationLocks::audit($reservation, $actor, $store->id, 'reservation_cancelled', "cancelled by {$store->code}", ['reason' => $reason]);

            return $reservation;
        });
    }
}
