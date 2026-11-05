<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Enums\AdjustmentReason;
use App\Enums\MovementType;
use App\Enums\RepairerType;
use App\Enums\RepairStatus;
use App\Enums\UnitStatus;
use App\Exceptions\Inventory\IdempotencyConflictException;
use App\Exceptions\Inventory\StaleUnitStateException;
use App\Models\DeviceRepair;
use App\Models\InventoryMovement;
use App\Models\ProductDefinition;
use App\Models\Store;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class StartRepair
{
    public const MAX_REPAIRER_NAME_LENGTH = 120;

    public function __construct(private readonly InventoryLedger $ledger) {}

    /**
     * Send a held used device to repair, in house or to a named external repairer.
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
        RepairerType $repairerType,
        ?string $repairerName,
        ?string $problem,
        string $idempotencyKey,
    ): InventoryMovement {
        Gate::forUser($actor)->authorize('serviceDevices', $store);

        $problem = UnitLifecycle::text($problem, 'problem', required: true);
        $repairerName = UnitLifecycle::text($repairerName, 'repairer_name', required: $repairerType === RepairerType::EXTERNAL, max: self::MAX_REPAIRER_NAME_LENGTH);

        $unit = $store->units()->whereKey($unitId)->firstOrFail();
        $unitIdLocked = $unit->id;

        try {
            return $this->ledger->record(
                $actor,
                $store,
                $unit->product_definition_id,
                'start_repair',
                $unit->id,
                $idempotencyKey,
                [
                    'expected_status' => $expectedStatus->value,
                    'expected_revision' => $expectedRevision,
                    'repairer_type' => $repairerType->value,
                    'repairer_name' => $repairerName,
                    'problem' => $problem,
                ],
                function (ProductDefinition $locked) use ($store, $unitId, $expectedStatus, $expectedRevision): array {
                    UnitLifecycle::assertUsedDevice($locked);
                    $unit = UnitLifecycle::lockExpected($store, $unitId, $expectedStatus, $expectedRevision);
                    UnitLifecycle::assertAllowed($unit, UnitLifecycle::START_REPAIR);

                    if ($unit->openRepair()->lockForUpdate()->exists()) {
                        throw ValidationException::withMessages(['unit' => __('devices.errors.repair_already_open')]);
                    }

                    $statusBefore = $unit->status;
                    UnitLifecycle::advance($unit, ['status' => UnitStatus::IN_REPAIR]);

                    return [
                        'type' => MovementType::REPAIR_STARTED,
                        'reason' => AdjustmentReason::REPAIR,
                        'inventory_unit_id' => $unit->id,
                        'quantity_change' => 0,
                        'unit_status_before' => $statusBefore,
                        'unit_status_after' => UnitStatus::IN_REPAIR,
                    ];
                },
                function (InventoryMovement $movement) use ($actor, $store, $unitIdLocked, $repairerType, $repairerName, $problem): void {
                    DeviceRepair::query()->create([
                        'inventory_unit_id' => $unitIdLocked,
                        'store_id' => $store->id,
                        'status' => RepairStatus::OPEN,
                        'repairer_type' => $repairerType,
                        'repairer_name' => $repairerName,
                        'problem' => $problem,
                        'started_movement_id' => $movement->id,
                        'started_by' => $actor->id,
                        'started_at' => $movement->occurred_at,
                    ]);
                },
            );
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['unit' => __('devices.errors.repair_already_open')]);
        }
    }
}
