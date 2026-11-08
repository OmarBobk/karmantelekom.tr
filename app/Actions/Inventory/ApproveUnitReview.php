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

class ApproveUnitReview
{
    public function __construct(private readonly InventoryLedger $ledger) {}

    /**
     * Make a reviewed found device sellable after its source was verified.
     *
     * The device was already counted as owned when it was found, so this is a zero-quantity
     * unit event. Rejecting a review is a removal (RemoveUnit) instead.
     *
     * @throws AuthorizationException
     * @throws ModelNotFoundException when the unit does not belong to the store.
     * @throws ValidationException
     * @throws StaleUnitStateException
     * @throws IdempotencyConflictException
     */
    public function __invoke(User $actor, Store $store, int $unitId, int $expectedRevision, ?string $verification, string $idempotencyKey): InventoryMovement
    {
        Gate::forUser($actor)->authorize('adjustInventory', $store);

        $note = InventoryLedger::prepareNote($verification);

        if ($note === null) {
            throw ValidationException::withMessages(['note' => __('inventory.errors.review_verification_required')]);
        }

        $unit = $store->units()->whereKey($unitId)->firstOrFail();

        return $this->ledger->record(
            $actor,
            $store,
            $unit->product_definition_id,
            'approve_unit_review',
            $unit->id,
            $idempotencyKey,
            ['expected_revision' => $expectedRevision, 'note' => $note],
            function (ProductDefinition $locked) use ($store, $unitId, $expectedRevision, $note): array {
                $unit = UnitLifecycle::lockExpected($store, $unitId, UnitStatus::REVIEW, $expectedRevision);
                UnitLifecycle::assertAllowed($unit, UnitLifecycle::APPROVE_REVIEW);

                UnitLifecycle::advance($unit, ['status' => UnitStatus::AVAILABLE]);

                return [
                    'type' => MovementType::REVIEW_APPROVED,
                    'reason' => AdjustmentReason::REVIEW,
                    'note' => $note,
                    'inventory_unit_id' => $unit->id,
                    'quantity_change' => 0,
                    'unit_status_before' => UnitStatus::REVIEW,
                    'unit_status_after' => UnitStatus::AVAILABLE,
                ];
            },
        );
    }
}
