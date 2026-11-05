<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Actions\Inventory\CloseRepair;
use App\Actions\Inventory\DeviceCostInput;
use App\Actions\Inventory\RecordInspection;
use App\Actions\Inventory\RegisterUnit;
use App\Actions\Inventory\StartRepair;
use App\Enums\InspectionOutcome;
use App\Enums\RepairerType;
use App\Enums\RepairStatus;
use App\Enums\UnitCondition;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\ProductDefinition;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Moves used devices through the real lifecycle actions, always with the unit's current state.
 */
trait InteractsWithDeviceLifecycle
{
    protected function registerDevice(User $actor, Store $store, ProductDefinition $definition, ?string $imei, ?string $serial = null, ?string $secondaryImei = null, UnitCondition $condition = UnitCondition::B): InventoryUnit
    {
        $movement = app(RegisterUnit::class)($actor, $store, $definition, $condition, $imei, $secondaryImei, $serial, null, (string) Str::uuid());

        return InventoryUnit::query()->findOrFail($movement->inventory_unit_id);
    }

    protected function inspectDevice(User $actor, InventoryUnit $unit, InspectionOutcome $outcome, ?UnitCondition $condition = null, ?string $findings = 'Checked'): InventoryMovement
    {
        $unit->refresh();

        return app(RecordInspection::class)($actor, $unit->store, $unit->id, $unit->status, $unit->lifecycle_revision, $outcome, $condition, $findings, (string) Str::uuid());
    }

    protected function startDeviceRepair(User $actor, InventoryUnit $unit, RepairerType $type = RepairerType::EXTERNAL, ?string $repairer = 'Fix Shop'): InventoryMovement
    {
        $unit->refresh();

        return app(StartRepair::class)($actor, $unit->store, $unit->id, $unit->status, $unit->lifecycle_revision, $type, $repairer, 'Cracked screen', (string) Str::uuid());
    }

    /**
     * @param  list<DeviceCostInput>  $costs
     */
    protected function closeDeviceRepair(User $actor, InventoryUnit $unit, RepairStatus $closeAs = RepairStatus::COMPLETED, bool $returned = true, array $costs = []): InventoryMovement
    {
        $unit->refresh();

        return app(CloseRepair::class)($actor, $unit->store, $unit->id, $unit->status, $unit->lifecycle_revision, $closeAs, 'Replaced screen', $returned, $costs, (string) Str::uuid());
    }

    /**
     * A used device that failed inspection and is waiting for repair.
     */
    protected function deviceNeedingRepair(User $actor, Store $store, ProductDefinition $definition, string $imei): InventoryUnit
    {
        $unit = $this->registerDevice($actor, $store, $definition, $imei);
        $this->inspectDevice($actor, $unit, InspectionOutcome::NEEDS_REPAIR, findings: 'Screen flicker');

        return $unit->refresh();
    }
}
