<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Enums\AdjustmentReason;
use App\Enums\InspectionOutcome;
use App\Enums\MovementType;
use App\Enums\TrackingMode;
use App\Enums\UnitCondition;
use App\Enums\UnitStatus;
use App\Exceptions\Inventory\IdempotencyConflictException;
use App\Exceptions\Inventory\StaleUnitStateException;
use App\Models\DeviceInspection;
use App\Models\InventoryMovement;
use App\Models\ProductDefinition;
use App\Models\Store;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RecordInspection
{
    public function __construct(private readonly InventoryLedger $ledger) {}

    /**
     * Record an inspection of an available or held used device.
     *
     * READY makes it available with the given grade (never FOR_PARTS). NEEDS_REPAIR holds it
     * in inspection. Either way the lifecycle revision advances.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     * @throws StaleUnitStateException
     * @throws IdempotencyConflictException
     */
    public function __invoke(
        User $actor,
        Store $store,
        int $unitId,
        UnitStatus $expectedStatus,
        int $expectedRevision,
        InspectionOutcome $outcome,
        ?UnitCondition $condition,
        ?string $findings,
        string $idempotencyKey,
    ): InventoryMovement {
        Gate::forUser($actor)->authorize('serviceDevices', $store);

        $findings = UnitLifecycle::text($findings, 'findings', required: $outcome === InspectionOutcome::NEEDS_REPAIR);

        if ($condition !== null && ! in_array($condition, UnitCondition::allowedFor(TrackingMode::USED_DEVICE), true)) {
            throw ValidationException::withMessages(['condition' => __('inventory.errors.condition_not_allowed')]);
        }

        if ($outcome === InspectionOutcome::READY && $condition === null) {
            throw ValidationException::withMessages(['condition' => __('devices.errors.required', ['attribute' => __('devices.fields.condition')])]);
        }

        if ($outcome === InspectionOutcome::READY && $condition === UnitCondition::FOR_PARTS) {
            throw ValidationException::withMessages(['condition' => __('devices.errors.for_parts_not_ready')]);
        }

        $unit = $store->units()->whereKey($unitId)->firstOrFail();
        $details = [];

        return $this->ledger->record(
            $actor,
            $store,
            $unit->product_definition_id,
            'record_inspection',
            $unit->id,
            $idempotencyKey,
            [
                'expected_status' => $expectedStatus->value,
                'expected_revision' => $expectedRevision,
                'outcome' => $outcome->value,
                'condition' => $condition?->value,
                'findings' => $findings,
            ],
            function (ProductDefinition $locked) use ($store, $unitId, $expectedStatus, $expectedRevision, $outcome, $condition, &$details): array {
                UnitLifecycle::assertUsedDevice($locked);
                $unit = UnitLifecycle::lockExpected($store, $unitId, $expectedStatus, $expectedRevision);
                UnitLifecycle::assertAllowed($unit, UnitLifecycle::INSPECT);

                $statusBefore = $unit->status;
                $conditionBefore = $unit->condition;
                $statusAfter = $outcome->resultingStatus();
                $conditionAfter = $condition ?? $conditionBefore;

                UnitLifecycle::advance($unit, ['status' => $statusAfter, 'condition' => $conditionAfter]);

                $details = ['unit_id' => $unit->id, 'condition_before' => $conditionBefore, 'condition_after' => $conditionAfter];

                return [
                    'type' => MovementType::INSPECTION,
                    'reason' => AdjustmentReason::INSPECTION,
                    'inventory_unit_id' => $unit->id,
                    'quantity_change' => 0,
                    'unit_status_before' => $statusBefore,
                    'unit_status_after' => $statusAfter,
                ];
            },
            function (InventoryMovement $movement) use ($actor, $store, $outcome, $findings, &$details): void {
                DeviceInspection::query()->create([
                    'inventory_unit_id' => $details['unit_id'],
                    'store_id' => $store->id,
                    'inventory_movement_id' => $movement->id,
                    'outcome' => $outcome,
                    'condition_before' => $details['condition_before'],
                    'condition_after' => $details['condition_after'],
                    'findings' => $findings,
                    'user_id' => $actor->id,
                    'inspected_at' => $movement->occurred_at,
                ]);
            },
        );
    }
}
