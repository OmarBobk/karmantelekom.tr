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

class MarkUnitFound
{
    public function __construct(private readonly InventoryLedger $ledger) {}

    /**
     * Return a removed device of this branch that was physically found to stock, awaiting review.
     *
     * The device is owned again (+1) but stays unsellable until an owner approves the review.
     * The form passes the revision it looked up; any later lifecycle change makes it stale.
     *
     * @throws AuthorizationException
     * @throws ModelNotFoundException when the unit does not belong to the store.
     * @throws ValidationException
     * @throws StaleUnitStateException
     * @throws IdempotencyConflictException
     */
    public function __invoke(User $actor, Store $store, int $unitId, int $expectedRevision, ?string $reason, string $idempotencyKey): InventoryMovement
    {
        Gate::forUser($actor)->authorize('adjustInventory', $store);

        $note = InventoryLedger::prepareNote($reason);

        if ($note === null) {
            throw ValidationException::withMessages(['note' => __('inventory.errors.reconcile_reason_required')]);
        }

        $unit = $store->units()->whereKey($unitId)->firstOrFail();

        return $this->ledger->record(
            $actor,
            $store,
            $unit->product_definition_id,
            'mark_unit_found',
            $unit->id,
            $idempotencyKey,
            ['expected_revision' => $expectedRevision, 'note' => $note],
            function (ProductDefinition $locked) use ($store, $unitId, $expectedRevision, $note): array {
                $unit = UnitLifecycle::lockExpected($store, $unitId, UnitStatus::REMOVED, $expectedRevision);
                UnitLifecycle::assertAllowed($unit, UnitLifecycle::MARK_FOUND);

                UnitLifecycle::advance($unit, ['status' => UnitStatus::REVIEW, 'removed_at' => null]);

                return [
                    'type' => MovementType::ADJUSTMENT_INCREASE,
                    'reason' => AdjustmentReason::FOUND,
                    'note' => $note,
                    'inventory_unit_id' => $unit->id,
                    'quantity_change' => 1,
                    'unit_status_before' => UnitStatus::REMOVED,
                    'unit_status_after' => UnitStatus::REVIEW,
                ];
            },
        );
    }
}
