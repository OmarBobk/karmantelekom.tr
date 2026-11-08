<?php

declare(strict_types=1);

namespace App\Actions\Network;

use App\Actions\Inventory\InventoryLedger;
use App\Enums\ReservationStatus;
use App\Models\CheckoutAttempt;
use App\Models\NetworkReservation;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Keeps counter sales and Store Network reservations from selling one device twice.
 *
 * An active reservation refuses every checkout of its device except one explicitly linked to
 * it. Payment starts and finalization check this under the device locks, in the checkout lock
 * order (attempt, definitions, devices, reservations); reservation changes take the same
 * device locks, so whichever commits first wins.
 */
final class ReservationGuard
{
    public function __construct(private readonly InventoryLedger $inventoryLedger) {}

    /**
     * Unlocked check when a cart is reviewed. Returns the error to show, or null.
     *
     * @param  list<int>  $unitIds
     */
    public static function reviewProblem(Store $store, array $unitIds, ?int $reservationId): ?string
    {
        $linked = null;

        if ($reservationId !== null) {
            $linked = NetworkReservation::query()->find($reservationId);

            if ($linked === null || ! in_array($linked->inventory_unit_id, $unitIds, true) || ! $linked->isActive() || $linked->isInTransit()
                || ! $store->units()->whereKey($linked->inventory_unit_id)->exists()) {
                return __('network.reservation.errors.link_invalid');
            }
        }

        $blocking = NetworkReservation::query()
            ->whereIn('active_unit_id', $unitIds)
            ->when($linked !== null, fn ($query) => $query->whereKeyNot($linked->id))
            ->get()
            ->first(fn (NetworkReservation $reservation): bool => $reservation->blocksSale());

        return $blocking === null ? null : self::reservedMessage($blocking);
    }

    /**
     * Lock the attempt's devices before a payment start freezes it, and refuse when a device is
     * reserved for another checkout or the linked reservation is no longer active. Call inside
     * the transaction, right after the attempt lock.
     *
     * @throws ValidationException
     */
    public function lockForPaymentStart(CheckoutAttempt $lockedAttempt): void
    {
        $unitIds = $lockedAttempt->unitIds();

        if ($unitIds === [] && $lockedAttempt->network_reservation_id === null) {
            return;
        }

        $definitionIds = collect($lockedAttempt->lines)->whereNotNull('unit_id')->pluck('definition_id')->all();
        $this->inventoryLedger->lockDefinitions($definitionIds);
        Store::query()->whereKey($lockedAttempt->store_id)->firstOrFail()->units()->whereKey($unitIds)->orderBy('id')->lockForUpdate()->get();

        self::lockedReservationFor($lockedAttempt, $unitIds);
    }

    /**
     * Under the device locks, the linked reservation the sale fulfills, or null for an ordinary
     * sale. Refuses when a device is reserved for another checkout, or the linked reservation
     * neither is active nor was active when this checkout's payment started.
     *
     * @param  list<int>  $unitIds
     *
     * @throws ValidationException
     */
    public static function lockedReservationFor(CheckoutAttempt $lockedAttempt, array $unitIds): ?NetworkReservation
    {
        $reservations = NetworkReservation::query()
            ->where(fn ($query) => $query->whereIn('active_unit_id', $unitIds)
                ->when($lockedAttempt->network_reservation_id !== null, fn ($linked) => $linked->orWhereKey($lockedAttempt->network_reservation_id)))
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $linked = null;

        foreach ($reservations as $reservation) {
            if ($reservation->id === $lockedAttempt->network_reservation_id) {
                $linked = $reservation;

                continue;
            }

            if ($reservation->blocksSale()) {
                throw ValidationException::withMessages(['reservation' => self::reservedMessage($reservation)]);
            }
        }

        if ($lockedAttempt->network_reservation_id === null) {
            return null;
        }

        if ($linked === null || ! in_array($linked->inventory_unit_id, $unitIds, true) || ! $linked->allowsLinkedSale($lockedAttempt)) {
            throw ValidationException::withMessages(['reservation' => __('network.reservation.errors.link_invalid')]);
        }

        return $linked;
    }

    /**
     * Close the linked reservation with the completed sale, inside the finalize transaction.
     */
    public static function fulfill(NetworkReservation $lockedReservation, Sale $sale, User $actor, Store $store): void
    {
        $lockedReservation->update([
            'status' => ReservationStatus::FULFILLED,
            'active_unit_id' => null,
            'sale_id' => $sale->id,
            'fulfilled_by' => $actor->id,
            'fulfilled_store_id' => $store->id,
            'fulfilled_at' => $sale->completed_at,
        ]);

        ReservationLocks::audit($lockedReservation, $actor, $store->id, 'reservation_fulfilled', "fulfilled by sale {$sale->number} at {$store->code}", ['sale_id' => $sale->id]);
    }

    private static function reservedMessage(NetworkReservation $reservation): string
    {
        $store = $reservation->requestingStore()->first(['name', 'code']);

        return $reservation->isInTransit()
            ? __('network.reservation.errors.device_in_transit')
            : __('network.reservation.errors.reserved_for', ['branch' => "{$store?->name} ({$store?->code})", 'until' => $reservation->expires_at?->format('d.m.Y H:i')]);
    }
}
