<?php

declare(strict_types=1);

namespace App\Actions\Network;

use App\Models\Store;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class SetReservationHold
{
    /**
     * Set how many minutes this branch holds a device after approving a reservation. Applies to
     * later approvals only; saving the current value writes nothing.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $actor, Store $store, mixed $minutes): Store
    {
        Gate::forUser($actor)->authorize('manageReservations', $store);

        $value = filter_var($minutes, FILTER_VALIDATE_INT, ['options' => ['min_range' => Store::MIN_RESERVATION_MINUTES, 'max_range' => Store::MAX_RESERVATION_MINUTES]]);

        if ($value === false) {
            throw ValidationException::withMessages(['reservation_minutes' => __('network.hold.errors.range', ['min' => Store::MIN_RESERVATION_MINUTES, 'max' => Store::MAX_RESERVATION_MINUTES])]);
        }

        if ($store->reservation_minutes === $value) {
            return $store;
        }

        $previous = $store->reservation_minutes;
        $store->forceFill(['reservation_minutes' => $value])->save();

        activity('store_network')
            ->performedOn($store)
            ->causedBy($actor)
            ->event('reservation_hold_changed')
            ->withProperties(['store_id' => $store->id, 'from' => $previous, 'to' => $value])
            ->log("Reservation hold for {$store->code} set to {$value} minutes");

        return $store;
    }
}
