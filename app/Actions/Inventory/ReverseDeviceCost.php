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

class ReverseDeviceCost
{
    public function __construct(private readonly InventoryLedger $ledger) {}

    /**
     * Reverse a recorded cost and, for a correction, record its replacement in the same transaction.
     *
     * The original stays on record with its reversal reason. A replacement keeps the original's
     * category and repair link and points back at the entry it replaces.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     * @throws IdempotencyConflictException
     */
    public function __invoke(
        User $actor,
        Store $store,
        int $unitId,
        int $entryId,
        ?string $reason,
        ?DeviceCostInput $replacement,
        string $idempotencyKey,
    ): InventoryMovement {
        Gate::forUser($actor)->authorize('manageDeviceCosts', $store);

        $reason = UnitLifecycle::text($reason, 'reversal_reason', required: true);
        $unit = $store->units()->whereKey($unitId)->firstOrFail();
        $details = [];

        return $this->ledger->record(
            $actor,
            $store,
            $unit->product_definition_id,
            'reverse_device_cost',
            $unit->id,
            $idempotencyKey,
            ['entry_id' => $entryId, 'reason' => $reason, 'replacement' => $replacement?->fingerprint()],
            function (ProductDefinition $locked) use ($store, $unitId, $entryId, &$details): array {
                UnitLifecycle::assertUsedDevice($locked);
                $unit = $store->units()->whereKey($unitId)->lockForUpdate()->firstOrFail();
                $entry = $unit->costEntries()->whereKey($entryId)->lockForUpdate()->first();

                if ($entry === null) {
                    throw ValidationException::withMessages(['reversal_reason' => __('devices.errors.cost_not_found')]);
                }

                if ($entry->isReversed()) {
                    throw ValidationException::withMessages(['reversal_reason' => __('devices.errors.cost_already_reversed')]);
                }

                $details = ['entry' => $entry, 'unit_id' => $unit->id];

                return [
                    'type' => MovementType::DEVICE_COST_REVERSED,
                    'reason' => AdjustmentReason::DEVICE_COST,
                    'inventory_unit_id' => $unit->id,
                    'quantity_change' => 0,
                    'unit_status_before' => $unit->status,
                    'unit_status_after' => $unit->status,
                ];
            },
            function (InventoryMovement $movement) use ($actor, $store, $reason, $replacement, &$details): void {
                /** @var DeviceCostEntry $entry */
                $entry = $details['entry'];

                $entry->update([
                    'reversed_at' => $movement->occurred_at,
                    'reversed_by' => $actor->id,
                    'reversal_reason' => $reason,
                    'reversal_movement_id' => $movement->id,
                ]);

                if ($replacement !== null) {
                    DeviceCostEntry::query()->create([
                        ...$replacement->withCategory($entry->category)->attributes(),
                        'inventory_unit_id' => $details['unit_id'],
                        'store_id' => $store->id,
                        'device_repair_id' => $entry->device_repair_id,
                        'inventory_movement_id' => $movement->id,
                        'replaces_entry_id' => $entry->id,
                        'user_id' => $actor->id,
                        'recorded_at' => $movement->occurred_at,
                    ]);
                }
            },
        );
    }
}
