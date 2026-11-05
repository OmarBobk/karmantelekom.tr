<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Enums\AdjustmentReason;
use App\Enums\MovementType;
use App\Enums\RepairStatus;
use App\Enums\UnitStatus;
use App\Exceptions\Inventory\IdempotencyConflictException;
use App\Exceptions\Inventory\StaleUnitStateException;
use App\Models\DeviceCostEntry;
use App\Models\DeviceRepair;
use App\Models\InventoryMovement;
use App\Models\ProductDefinition;
use App\Models\Store;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class CloseRepair
{
    public function __construct(private readonly InventoryLedger $ledger) {}

    /**
     * Complete or cancel a unit's open repair. The device always returns to inspection.
     *
     * An external repair can only be closed once the operator confirms the device is
     * physically back. Costs may be recorded with a completion, in the same transaction,
     * only by users allowed to record device costs; without them costing stays pending.
     *
     * @param  list<DeviceCostInput>  $costs
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
        RepairStatus $closeAs,
        ?string $text,
        bool $returnConfirmed,
        array $costs,
        string $idempotencyKey,
    ): InventoryMovement {
        if ($closeAs === RepairStatus::OPEN) {
            throw new InvalidArgumentException('A repair can only be closed as completed or cancelled.');
        }

        Gate::forUser($actor)->authorize('serviceDevices', $store);

        if ($costs !== []) {
            if ($closeAs !== RepairStatus::COMPLETED) {
                throw new InvalidArgumentException('Costs can only be recorded when completing a repair.');
            }

            Gate::forUser($actor)->authorize('manageDeviceCosts', $store);
        }

        $completing = $closeAs === RepairStatus::COMPLETED;
        $text = UnitLifecycle::text($text, $completing ? 'work_performed' : 'close_reason', required: true);

        $unit = $store->units()->whereKey($unitId)->firstOrFail();
        $details = [];

        return $this->ledger->record(
            $actor,
            $store,
            $unit->product_definition_id,
            $completing ? 'complete_repair' : 'cancel_repair',
            $unit->id,
            $idempotencyKey,
            [
                'expected_status' => $expectedStatus->value,
                'expected_revision' => $expectedRevision,
                'text' => $text,
                'return_confirmed' => $returnConfirmed,
                'costs' => array_map(fn (DeviceCostInput $cost): array => $cost->fingerprint(), $costs),
            ],
            function (ProductDefinition $locked) use ($store, $unitId, $expectedStatus, $expectedRevision, $returnConfirmed, $completing, &$details): array {
                UnitLifecycle::assertUsedDevice($locked);
                $unit = UnitLifecycle::lockExpected($store, $unitId, $expectedStatus, $expectedRevision);
                UnitLifecycle::assertAllowed($unit, UnitLifecycle::CLOSE_REPAIR);

                $repair = $unit->openRepair()->lockForUpdate()->first();

                if ($repair === null) {
                    throw ValidationException::withMessages(['unit' => __('devices.errors.no_open_repair')]);
                }

                if ($repair->isExternal() && ! $returnConfirmed) {
                    throw ValidationException::withMessages(['returned' => __('devices.errors.return_confirmation_required')]);
                }

                UnitLifecycle::advance($unit, ['status' => UnitStatus::INSPECTION]);
                $details = ['repair' => $repair, 'unit_id' => $unit->id];

                return [
                    'type' => $completing ? MovementType::REPAIR_COMPLETED : MovementType::REPAIR_CANCELLED,
                    'reason' => AdjustmentReason::REPAIR,
                    'inventory_unit_id' => $unit->id,
                    'quantity_change' => 0,
                    'unit_status_before' => UnitStatus::IN_REPAIR,
                    'unit_status_after' => UnitStatus::INSPECTION,
                ];
            },
            function (InventoryMovement $movement) use ($actor, $store, $closeAs, $completing, $text, $costs, &$details): void {
                /** @var DeviceRepair $repair */
                $repair = $details['repair'];

                $repair->update([
                    'status' => $closeAs,
                    'work_performed' => $completing ? $text : null,
                    'close_reason' => $completing ? null : $text,
                    'closed_movement_id' => $movement->id,
                    'closed_by' => $actor->id,
                    'returned_at' => $repair->isExternal() ? $movement->occurred_at : null,
                    'closed_at' => $movement->occurred_at,
                ]);

                foreach ($costs as $cost) {
                    DeviceCostEntry::query()->create([
                        ...$cost->attributes(),
                        'inventory_unit_id' => $details['unit_id'],
                        'store_id' => $store->id,
                        'device_repair_id' => $repair->id,
                        'inventory_movement_id' => $movement->id,
                        'user_id' => $actor->id,
                        'recorded_at' => $movement->occurred_at,
                    ]);
                }
            },
        );
    }
}
