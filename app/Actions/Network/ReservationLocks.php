<?php

declare(strict_types=1);

namespace App\Actions\Network;

use App\Actions\Pos\FinalizeCheckout;
use App\Enums\CheckoutAttemptStatus;
use App\Enums\ReservationStatus;
use App\Models\CheckoutAttempt;
use App\Models\InventoryUnit;
use App\Models\NetworkListing;
use App\Models\NetworkReservation;
use App\Models\ProductDefinition;
use App\Models\User;
use App\Services\Idempotency;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The lock order every reservation change takes: product definition, device, listing, then the
 * device's open reservations. Checkout finalization locks definitions and devices in the same
 * order before it locks reservations, so the two serialize on the device.
 *
 * The device is locked by id, not through a branch, because a transfer moves it; callers check
 * which branch holds it.
 */
final class ReservationLocks
{
    /**
     * @template TResult
     *
     * @param  Closure(ProductDefinition, InventoryUnit, ?NetworkListing, Collection<int, NetworkReservation>): TResult  $change
     * @return TResult
     */
    public static function forUnit(int $unitId, Closure $change): mixed
    {
        $definitionId = InventoryUnit::query()->whereKey($unitId)->value('product_definition_id') ?? abort(404);

        return DB::transaction(function () use ($unitId, $definitionId, $change): mixed {
            $definition = ProductDefinition::query()->whereKey($definitionId)->lockForUpdate()->firstOrFail();
            $unit = InventoryUnit::query()->whereKey($unitId)->where('product_definition_id', $definition->id)->lockForUpdate()->firstOrFail();
            $listing = NetworkListing::query()->where('inventory_unit_id', $unit->id)->lockForUpdate()->first();
            $open = NetworkReservation::query()
                ->where('inventory_unit_id', $unit->id)
                ->whereIn('status', [ReservationStatus::PENDING, ReservationStatus::APPROVED])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            return $change($definition, $unit, $listing, $open);
        }, FinalizeCheckout::DEADLOCK_ATTEMPTS);
    }

    /**
     * @template TResult
     *
     * @param  Closure(NetworkReservation, ProductDefinition, InventoryUnit, ?NetworkListing, Collection<int, NetworkReservation>): TResult  $change
     * @return TResult
     */
    public static function run(NetworkReservation $reservation, Closure $change): mixed
    {
        return self::forUnit($reservation->inventory_unit_id, function (ProductDefinition $definition, InventoryUnit $unit, ?NetworkListing $listing, Collection $open) use ($reservation, $change): mixed {
            $current = $open->firstWhere('id', $reservation->id)
                ?? NetworkReservation::query()->whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            return $change($current, $definition, $unit, $listing, $open);
        });
    }

    /**
     * Whether an open checkout holding the device has started payment (a terminal charge or a
     * cash intake froze it).
     *
     * Call under the device lock and before any other non-locking read in the transaction.
     * Payment starts take the same device lock before freezing, so this first consistent read
     * sees every start that committed before the lock was granted, and a later start sees the
     * reservation change through its own locking read.
     */
    public static function paymentStartedFor(InventoryUnit $lockedUnit): bool
    {
        return CheckoutAttempt::query()
            ->where('status', CheckoutAttemptStatus::OPEN)
            ->whereNotNull('frozen_at')
            ->whereJsonContains('lines', ['unit_id' => $lockedUnit->id])
            ->exists();
    }

    /**
     * Approved reservations of the device past expiry and not in transit are stored as expired,
     * releasing the one-active-reservation slot.
     *
     * @param  Collection<int, NetworkReservation>  $open
     */
    public static function closeExpired(Collection $open): void
    {
        foreach ($open as $reservation) {
            if ($reservation->isApproved() && ! $reservation->isInTransit() && ! $reservation->isActive()) {
                $reservation->update([
                    'status' => ReservationStatus::EXPIRED,
                    'active_unit_id' => null,
                    'expired_at' => $reservation->expires_at,
                ]);
            }
        }
    }

    /**
     * Trim a note; blank means absent unless required.
     *
     * @throws ValidationException
     */
    public static function note(?string $value, string $field, bool $required): ?string
    {
        $value = Str::trim((string) $value);

        if ($required && $value === '') {
            throw ValidationException::withMessages([$field => __('network.reservation.errors.reason_required')]);
        }

        if (mb_strlen($value) > NetworkReservation::MAX_NOTE_LENGTH) {
            throw ValidationException::withMessages([$field => __('network.reservation.errors.note_too_long', ['max' => NetworkReservation::MAX_NOTE_LENGTH])]);
        }

        return $value === '' ? null : $value;
    }

    /**
     * Record one reservation transition in the Store Network audit log.
     *
     * @param  array<string, mixed>  $properties
     */
    public static function audit(NetworkReservation $reservation, User $actor, int $storeId, string $event, string $description, array $properties = []): void
    {
        activity('store_network')
            ->performedOn($reservation)
            ->causedBy($actor)
            ->event($event)
            ->withProperties([
                'store_id' => $storeId,
                'inventory_unit_id' => $reservation->inventory_unit_id,
                'owning_store_id' => $reservation->owning_store_id,
                'requesting_store_id' => $reservation->requesting_store_id,
                'status' => $reservation->status->value,
                ...$properties,
            ])
            ->log("Reservation #{$reservation->id}: {$description}");
    }

    /**
     * Idempotency fingerprint for a reservation's inventory movement.
     */
    public static function fingerprint(NetworkReservation $reservation, string $operation): string
    {
        return Idempotency::fingerprint(['operation' => $operation, 'reservation' => $reservation->id]);
    }
}
