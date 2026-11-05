<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Enums\AdjustmentReason;
use App\Enums\MovementType;
use App\Exceptions\Inventory\IdempotencyConflictException;
use App\Models\DeviceCostEntry;
use App\Models\InventoryMovement;
use App\Models\ProductDefinition;
use App\Models\Store;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RecordDeviceCost
{
    public function __construct(private readonly InventoryLedger $ledger) {}

    /**
     * Record one attributable cost against a used device, optionally linked to one of its repairs.
     *
     * Costs never change status or stock, and a part cost does not consume part inventory.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     * @throws IdempotencyConflictException
     */
    public function __invoke(User $actor, Store $store, int $unitId, DeviceCostInput $cost, ?int $repairId, string $idempotencyKey): InventoryMovement
    {
        Gate::forUser($actor)->authorize('manageDeviceCosts', $store);

        $unit = $store->units()->whereKey($unitId)->firstOrFail();
        $details = [];

        return $this->ledger->record(
            $actor,
            $store,
            $unit->product_definition_id,
            'record_device_cost',
            $unit->id,
            $idempotencyKey,
            ['cost' => $cost->fingerprint(), 'repair_id' => $repairId],
            function (ProductDefinition $locked) use ($store, $unitId, $repairId, &$details): array {
                UnitLifecycle::assertUsedDevice($locked);
                $unit = $store->units()->whereKey($unitId)->lockForUpdate()->firstOrFail();

                if ($repairId !== null && ! $unit->repairs()->whereKey($repairId)->exists()) {
                    throw ValidationException::withMessages(['cost_repair' => __('devices.errors.repair_not_found')]);
                }

                $details = ['unit_id' => $unit->id];

                return [
                    'type' => MovementType::DEVICE_COST_RECORDED,
                    'reason' => AdjustmentReason::DEVICE_COST,
                    'inventory_unit_id' => $unit->id,
                    'quantity_change' => 0,
                    'unit_status_before' => $unit->status,
                    'unit_status_after' => $unit->status,
                ];
            },
            function (InventoryMovement $movement) use ($actor, $store, $cost, $repairId, &$details): void {
                DeviceCostEntry::query()->create([
                    ...$cost->attributes(),
                    'inventory_unit_id' => $details['unit_id'],
                    'store_id' => $store->id,
                    'device_repair_id' => $repairId,
                    'inventory_movement_id' => $movement->id,
                    'user_id' => $actor->id,
                    'recorded_at' => $movement->occurred_at,
                ]);
            },
        );
    }
}
