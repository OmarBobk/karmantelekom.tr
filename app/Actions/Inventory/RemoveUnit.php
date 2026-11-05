<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Enums\AdjustmentReason;
use App\Enums\MovementType;
use App\Enums\UnitStatus;
use App\Exceptions\Inventory\IdempotencyConflictException;
use App\Exceptions\Inventory\StaleUnitStateException;
use App\Models\InventoryMovement;
use App\Models\ProductDefinition;
use App\Models\Store;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RemoveUnit
{
    public function __construct(private readonly InventoryLedger $ledger) {}

    /**
     * Remove an available unit, or a used device held in inspection, from stock. The unit and
     * its identifiers are kept. A unit with an open repair cannot be removed.
     *
     * A form opened on the unit page passes the revision it saw, so a stale form is rejected
     * even when the device has since returned to the same status.
     *
     * @throws AuthorizationException
     * @throws ModelNotFoundException when the unit does not belong to the store.
     * @throws ValidationException
     * @throws StaleUnitStateException
     * @throws IdempotencyConflictException
     */
    public function __invoke(
        User $actor,
        Store $store,
        int $unitId,
        AdjustmentReason $reason,
        ?string $note,
        string $idempotencyKey,
        ?int $expectedRevision = null,
    ): InventoryMovement {
        Gate::forUser($actor)->authorize('adjustInventory', $store);

        if (! MovementType::ADJUSTMENT_DECREASE->allows($reason)) {
            throw ValidationException::withMessages(['reason' => __('inventory.errors.reason_mismatch')]);
        }

        $note = InventoryLedger::prepareNote($note, required: true);

        $unit = $store->units()->whereKey($unitId)->firstOrFail();

        $payload = ['reason' => $reason->value, 'note' => $note];

        if ($expectedRevision !== null) {
            $payload['expected_revision'] = $expectedRevision;
        }

        return $this->ledger->record(
            $actor,
            $store,
            $unit->product_definition_id,
            'remove_unit',
            $unit->id,
            $idempotencyKey,
            $payload,
            function (ProductDefinition $locked) use ($store, $unitId, $reason, $note, $expectedRevision): array {
                $unit = $store->units()->whereKey($unitId)->lockForUpdate()->firstOrFail();

                if ($expectedRevision !== null && $unit->lifecycle_revision !== $expectedRevision) {
                    throw new StaleUnitStateException($unit->status, $unit->lifecycle_revision);
                }

                if ($unit->status === UnitStatus::IN_REPAIR) {
                    throw ValidationException::withMessages(['reason' => __('devices.errors.remove_during_repair')]);
                }

                if (! $unit->status->allowsRemoval()) {
                    throw ValidationException::withMessages(['reason' => __('inventory.errors.unit_not_available')]);
                }

                $statusBefore = $unit->status;
                UnitLifecycle::advance($unit, ['status' => UnitStatus::REMOVED, 'removed_at' => now()]);

                return [
                    'type' => MovementType::ADJUSTMENT_DECREASE,
                    'reason' => $reason,
                    'note' => $note,
                    'inventory_unit_id' => $unit->id,
                    'quantity_change' => -1,
                    'unit_status_before' => $statusBefore,
                    'unit_status_after' => UnitStatus::REMOVED,
                ];
            },
        );
    }
}
