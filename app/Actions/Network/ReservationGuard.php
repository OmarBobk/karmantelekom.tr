<?php

declare(strict_types=1);

namespace App\Actions\Network;

use App\Actions\Inventory\InventoryLedger;
use App\Actions\Storefront\BranchOrderHolds;
use App\Enums\ReservationStatus;
use App\Enums\UnitStatus;
use App\Models\CheckoutAttempt;
use App\Models\InventoryBalance;
use App\Models\InventoryUnit;
use App\Models\NetworkReservation;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Keeps counter sales and Store Network reservations from selling one device twice.
 *
 * An active reservation refuses every checkout of its device except one explicitly linked to
 * it. When that checkout starts payment it claims the reservation: from then until the sale
 * completes or its funds are resolved, no other checkout may start payment on or sell the
 * device, even after the hold's nominal expiry. Payment starts and finalization check this
 * under the device locks, in the checkout lock order (attempt, definitions, devices,
 * reservations); reservation changes take the same device locks, so whichever commits first wins.
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
                || $linked->hasLiveClaim() || ! $store->units()->whereKey($linked->inventory_unit_id)->exists()) {
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
     * Before a terminal payment or cash intake starts, lock the attempt's stock and recheck it:
     * each device must still be in the checkout's branch, on the reviewed product, available, and
     * at the reviewed revision, and none may be reserved for or claimed by another checkout; no
     * device or quantity may be held for a storefront order other than the one it collects. A
     * linked reservation or storefront order is then claimed for this checkout. Call inside the
     * transaction, right after the attempt lock, on every start; a repeated start keeps the claim.
     *
     * Lock order: the linked storefront order, product definitions, the branch's quantity
     * balances, devices, reservations, then storefront order holds.
     *
     * @throws ValidationException
     */
    public function lockForPaymentStart(CheckoutAttempt $lockedAttempt, User $actor): void
    {
        $lines = collect($lockedAttempt->lines);
        $unitLines = $lines->whereNotNull('unit_id');
        $unitIds = $lockedAttempt->unitIds();
        $storefrontOrder = BranchOrderHolds::lockLinkedOrder($lockedAttempt);

        $this->inventoryLedger->lockDefinitions($lines->pluck('definition_id')->map(fn ($id): int => (int) $id)->all());
        $balances = InventoryBalance::query()
            ->where('store_id', $lockedAttempt->store_id)
            ->whereIn('product_definition_id', $lines->whereNull('unit_id')->pluck('definition_id')->map(fn ($id): int => (int) $id)->unique()->sort()->values()->all())
            ->orderBy('product_definition_id')
            ->lockForUpdate()
            ->get()
            ->keyBy('product_definition_id');
        $units = InventoryUnit::query()->whereKey($unitIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $changed = [];

        foreach ($unitLines as $line) {
            $unit = $units->get((int) $line['unit_id']);

            if ($unit === null || $unit->store_id !== $lockedAttempt->store_id || $unit->product_definition_id !== (int) $line['definition_id']
                || $unit->status !== UnitStatus::AVAILABLE || $unit->lifecycle_revision !== (int) $line['unit_revision']) {
                $changed[] = __('pos.errors.unit_changed', [
                    'name' => $line['name'],
                    'status' => $unit === null ? __('pos.errors.unit_missing') : ($unit->store_id !== $lockedAttempt->store_id ? __('network.reservation.errors.other_branch') : $unit->status->label()),
                ]);
            }
        }

        if ($changed !== []) {
            throw ValidationException::withMessages(['attempt' => $changed]);
        }

        $linked = self::lockedReservationFor($lockedAttempt, $unitIds);

        if ($linked !== null && $linked->claimed_attempt_id !== $lockedAttempt->id) {
            $linked->update(['claimed_attempt_id' => $lockedAttempt->id, 'claimed_at' => now()]);

            ReservationLocks::audit($linked, $actor, $lockedAttempt->store_id, 'reservation_claimed', "payment started by checkout {$lockedAttempt->key}", ['checkout_attempt_id' => $lockedAttempt->id]);
        }

        BranchOrderHolds::assertSellable($lockedAttempt, $storefrontOrder, $balances);

        if ($storefrontOrder !== null) {
            BranchOrderHolds::claim($storefrontOrder, $lockedAttempt, $actor);
        }
    }

    /**
     * Under the device locks, the linked reservation the sale fulfills, or null for an ordinary
     * sale. Refuses when a device is reserved for or claimed by another checkout, or the linked
     * reservation is neither active nor claimed by this checkout.
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
     * Release reservations claimed by this checkout once its funds are resolved: the terminal
     * payment was cleared, the cash intake released, or the checkout cancelled or voided. Call
     * inside that resolution's transaction, after the attempt lock.
     */
    public static function releaseClaim(CheckoutAttempt $lockedAttempt, User $actor): void
    {
        BranchOrderHolds::releaseClaim($lockedAttempt, $actor);

        $claimed = NetworkReservation::query()->where('claimed_attempt_id', $lockedAttempt->id)->lockForUpdate()->get();

        foreach ($claimed as $reservation) {
            if (! $reservation->isApproved()) {
                continue;
            }

            $reservation->update(['claimed_attempt_id' => null, 'claimed_at' => null]);

            ReservationLocks::audit($reservation, $actor, $lockedAttempt->store_id, 'reservation_claim_released', "payment claim of checkout {$lockedAttempt->key} released", ['checkout_attempt_id' => $lockedAttempt->id]);
        }
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

        if ($reservation->isInTransit()) {
            return __('network.reservation.errors.device_in_transit');
        }

        if (! $reservation->isActive()) {
            return __('network.reservation.errors.claimed', ['branch' => "{$store?->name} ({$store?->code})"]);
        }

        return __('network.reservation.errors.reserved_for', ['branch' => "{$store?->name} ({$store?->code})", 'until' => $reservation->expires_at?->format('d.m.Y H:i')]);
    }
}
