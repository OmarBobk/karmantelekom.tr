<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Enums\AdjustmentReason;
use App\Enums\MovementType;
use App\Enums\UnitStatus;
use App\Exceptions\Inventory\IdempotencyConflictException;
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
     * Remove an available unit from stock. The unit and its identifiers are kept.
     *
     * @throws AuthorizationException
     * @throws ModelNotFoundException when the unit does not belong to the store.
     * @throws ValidationException
     * @throws IdempotencyConflictException
     */
    public function __invoke(
        User $actor,
        Store $store,
        int $unitId,
        AdjustmentReason $reason,
        ?string $note,
        string $idempotencyKey,
    ): InventoryMovement {
        Gate::forUser($actor)->authorize('adjustInventory', $store);

        if (! MovementType::ADJUSTMENT_DECREASE->allows($reason)) {
            throw ValidationException::withMessages(['reason' => __('inventory.errors.reason_mismatch')]);
        }

        $note = InventoryLedger::prepareNote($note, required: true);

        $unit = $store->units()->whereKey($unitId)->firstOrFail();

        return $this->ledger->record(
            $actor,
            $store,
            $unit->product_definition_id,
            'remove_unit',
            $unit->id,
            $idempotencyKey,
            ['reason' => $reason->value, 'note' => $note],
            function (ProductDefinition $locked) use ($store, $unitId, $reason, $note): array {
                $unit = $store->units()->whereKey($unitId)->lockForUpdate()->firstOrFail();

                if ($unit->status !== UnitStatus::AVAILABLE) {
                    throw ValidationException::withMessages(['reason' => __('inventory.errors.unit_not_available')]);
                }

                $unit->update(['status' => UnitStatus::REMOVED, 'removed_at' => now()]);

                return [
                    'type' => MovementType::ADJUSTMENT_DECREASE,
                    'reason' => $reason,
                    'note' => $note,
                    'inventory_unit_id' => $unit->id,
                    'quantity_change' => -1,
                    'unit_status_before' => UnitStatus::AVAILABLE,
                    'unit_status_after' => UnitStatus::REMOVED,
                ];
            },
        );
    }
}
