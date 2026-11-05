<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Inventory\DeviceCostInput;
use App\Actions\Inventory\RecordInspection;
use App\Actions\Inventory\RemoveUnit;
use App\Actions\Inventory\StartRepair;
use App\Enums\AdjustmentReason;
use App\Enums\InspectionOutcome;
use App\Enums\RepairerType;
use App\Enums\RepairStatus;
use App\Enums\TrackingMode;
use App\Enums\UnitCondition;
use App\Enums\UnitStatus;
use App\Exceptions\Inventory\IdempotencyConflictException;
use App\Exceptions\Inventory\StaleUnitStateException;
use App\Models\DeviceCostEntry;
use App\Models\DeviceInspection;
use App\Models\DeviceRepair;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\ProductDefinition;
use App\Models\Store;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Support\InteractsWithDeviceLifecycle;
use Tests\Support\InteractsWithOperationalInventory;
use Tests\TestCase;

class DeviceLifecycleTest extends TestCase
{
    use InteractsWithDeviceLifecycle;
    use InteractsWithOperationalInventory;
    use RefreshDatabase;

    private User $owner;

    private Store $store;

    private ProductDefinition $usedPhone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOperationalRoles();
        $this->owner = $this->createOwner();
        $this->store = Store::factory()->create(['code' => 'KT01']);
        $this->usedPhone = ProductDefinition::factory()->create(['name' => 'iPhone 13', 'tracking_mode' => TrackingMode::USED_DEVICE]);
    }

    #[Test]
    public function a_device_goes_through_inspection_and_repair_back_to_available_stock(): void
    {
        $unit = $this->registerDevice($this->owner, $this->store, $this->usedPhone, self::imei('35693803564380'));
        $this->assertSame(0, $unit->lifecycle_revision);

        $this->inspectDevice($this->owner, $unit, InspectionOutcome::NEEDS_REPAIR, UnitCondition::C, 'Screen flicker');
        $this->assertUnitState($unit, UnitStatus::INSPECTION, 1, UnitCondition::C);

        $this->startDeviceRepair($this->owner, $unit);
        $this->assertUnitState($unit, UnitStatus::IN_REPAIR, 2, UnitCondition::C);
        $this->assertSame(0, $this->availableUnits());

        $this->closeDeviceRepair($this->owner, $unit, costs: [DeviceCostInput::fromInput('repair_parts', 'Screen', '1250.50', 'TRY', false)]);
        $this->assertUnitState($unit, UnitStatus::INSPECTION, 3, UnitCondition::C);

        $repair = DeviceRepair::query()->sole();
        $this->assertSame(RepairStatus::COMPLETED, $repair->status);
        $this->assertNotNull($repair->returned_at);
        $this->assertSame('1250.50', DeviceCostEntry::query()->sole()->amount);
        $this->assertSame($repair->id, DeviceCostEntry::query()->sole()->device_repair_id);

        $this->inspectDevice($this->owner, $unit, InspectionOutcome::READY, UnitCondition::A);
        $this->assertUnitState($unit, UnitStatus::AVAILABLE, 4, UnitCondition::A);
        $this->assertSame(1, $this->availableUnits());

        $events = InventoryMovement::query()->where('quantity_change', 0)->orderBy('id')->get();
        $this->assertSame(['inspection', 'repair_started', 'repair_completed', 'inspection'], $events->map(fn ($movement) => $movement->type->value)->all());
        $this->assertTrue($events->every(fn ($movement) => $movement->quantity_after === null && $movement->inventory_unit_id === $unit->id));
        $this->assertSame(UnitStatus::AVAILABLE, InventoryMovement::query()->latest('id')->first()->unit_status_after);
        $this->assertSame(1, (int) InventoryMovement::query()->sum('quantity_change'));
        $this->assertSame(2, DeviceInspection::count());
    }

    #[Test]
    public function in_house_repairs_close_without_return_confirmation_but_external_ones_require_it(): void
    {
        $external = $this->deviceNeedingRepair($this->owner, $this->store, $this->usedPhone, self::imei('35693803564380'));
        $this->startDeviceRepair($this->owner, $external);

        foreach ([RepairStatus::COMPLETED, RepairStatus::CANCELLED] as $closeAs) {
            $this->assertValidationErrorOn('returned', fn () => $this->closeDeviceRepair($this->owner, $external, $closeAs, returned: false));
            $this->assertUnitState($external, UnitStatus::IN_REPAIR, 2);
        }

        $this->closeDeviceRepair($this->owner, $external, RepairStatus::CANCELLED, returned: true);
        $this->assertUnitState($external, UnitStatus::INSPECTION, 3);
        $this->assertSame(RepairStatus::CANCELLED, DeviceRepair::query()->sole()->status);

        $inHouse = $this->deviceNeedingRepair($this->owner, $this->store, $this->usedPhone, self::imei('35693803564381'));
        $this->startDeviceRepair($this->owner, $inHouse, RepairerType::IN_HOUSE, null);
        $this->closeDeviceRepair($this->owner, $inHouse, returned: false);
        $this->assertUnitState($inHouse, UnitStatus::INSPECTION, 3);
        $this->assertNull(DeviceRepair::query()->where('inventory_unit_id', $inHouse->id)->sole()->returned_at);
    }

    #[Test]
    public function closing_a_repair_keeps_the_original_start_and_inspection_times(): void
    {
        $this->travelTo(now()->subDays(3)->startOfMinute());
        $unit = $this->deviceNeedingRepair($this->owner, $this->store, $this->usedPhone, self::imei('35693803564380'));
        $this->startDeviceRepair($this->owner, $unit);
        $startedAt = DeviceRepair::query()->sole()->started_at;
        $inspectedAt = DeviceInspection::query()->sole()->inspected_at;
        $this->travelBack();

        $this->closeDeviceRepair($this->owner, $unit);

        $repair = DeviceRepair::query()->sole();
        $this->assertTrue($repair->started_at->equalTo($startedAt));
        $this->assertTrue($repair->closed_at->greaterThan($startedAt));
        $this->assertTrue(DeviceInspection::query()->sole()->inspected_at->equalTo($inspectedAt));
    }

    #[Test]
    public function invalid_transitions_are_rejected_without_writing_anything(): void
    {
        $available = $this->registerDevice($this->owner, $this->store, $this->usedPhone, self::imei('35693803564380'));
        $inRepair = $this->deviceNeedingRepair($this->owner, $this->store, $this->usedPhone, self::imei('35693803564381'));
        $this->startDeviceRepair($this->owner, $inRepair);
        $removed = $this->registerDevice($this->owner, $this->store, $this->usedPhone, self::imei('35693803564382'));
        app(RemoveUnit::class)($this->owner, $this->store, $removed->id, AdjustmentReason::DAMAGED, 'Broken', 'remove-1', 0);
        $movements = InventoryMovement::count();

        $this->assertValidationErrorOn('unit', fn () => $this->startDeviceRepair($this->owner, $available));
        $this->assertValidationErrorOn('unit', fn () => $this->closeDeviceRepair($this->owner, $available));
        $this->assertValidationErrorOn('unit', fn () => $this->inspectDevice($this->owner, $inRepair, InspectionOutcome::READY, UnitCondition::A));
        $this->assertValidationErrorOn('unit', fn () => $this->startDeviceRepair($this->owner, $inRepair));
        $this->assertValidationErrorOn('unit', fn () => $this->inspectDevice($this->owner, $removed, InspectionOutcome::READY, UnitCondition::A));
        $this->assertValidationErrorOn('unit', fn () => $this->startDeviceRepair($this->owner, $removed));
        $this->assertValidationErrorOn('reason', fn () => app(RemoveUnit::class)($this->owner, $this->store, $inRepair->id, AdjustmentReason::DAMAGED, 'Broken', 'remove-2', 2));
        $this->assertValidationErrorOn('reason', fn () => app(RemoveUnit::class)($this->owner, $this->store, $removed->id, AdjustmentReason::DAMAGED, 'Again', 'remove-3', 1));

        $this->assertSame($movements, InventoryMovement::count());
        $this->assertSame(UnitStatus::IN_REPAIR, $inRepair->fresh()->status);
        $this->assertSame(UnitStatus::REMOVED, $removed->fresh()->status);
    }

    #[Test]
    public function a_device_waiting_for_inspection_can_be_removed_and_stays_removed(): void
    {
        $unit = $this->deviceNeedingRepair($this->owner, $this->store, $this->usedPhone, self::imei('35693803564380'));

        $movement = app(RemoveUnit::class)($this->owner, $this->store, $unit->id, AdjustmentReason::DAMAGED, 'Beyond repair', 'remove-1', 1);

        $this->assertSame(UnitStatus::INSPECTION, $movement->unit_status_before);
        $this->assertSame(-1, $movement->quantity_change);
        $this->assertUnitState($unit, UnitStatus::REMOVED, 2);
        $this->assertSame(0, (int) InventoryMovement::query()->sum('quantity_change'));
    }

    #[Test]
    public function a_for_parts_grade_cannot_be_marked_ready(): void
    {
        $unit = $this->registerDevice($this->owner, $this->store, $this->usedPhone, self::imei('35693803564380'));

        $this->assertValidationErrorOn('condition', fn () => $this->inspectDevice($this->owner, $unit, InspectionOutcome::READY, UnitCondition::FOR_PARTS));
        $this->assertValidationErrorOn('condition', fn () => $this->inspectDevice($this->owner, $unit, InspectionOutcome::READY, null));
        $this->assertValidationErrorOn('findings', fn () => $this->inspectDevice($this->owner, $unit, InspectionOutcome::NEEDS_REPAIR, findings: '  '));

        $this->inspectDevice($this->owner, $unit, InspectionOutcome::NEEDS_REPAIR, UnitCondition::FOR_PARTS, 'Board dead');
        $this->assertUnitState($unit, UnitStatus::INSPECTION, 1, UnitCondition::FOR_PARTS);
        $this->assertSame(0, DeviceRepair::count());
    }

    #[Test]
    public function inspection_and_repair_apply_only_to_used_devices(): void
    {
        $newPhone = ProductDefinition::factory()->create(['tracking_mode' => TrackingMode::SERIALIZED]);
        $unit = $this->registerDevice($this->owner, $this->store, $newPhone, self::imei('35693803564380'), condition: UnitCondition::NEW);

        $this->assertValidationErrorOn('unit', fn () => $this->inspectDevice($this->owner, $unit, InspectionOutcome::NEEDS_REPAIR));
        $this->assertSame(1, InventoryMovement::count());
    }

    #[Test]
    public function a_form_opened_before_the_device_left_and_returned_to_the_same_status_is_rejected(): void
    {
        $unit = $this->deviceNeedingRepair($this->owner, $this->store, $this->usedPhone, self::imei('35693803564380'));
        $openedRevision = $unit->lifecycle_revision;

        $this->startDeviceRepair($this->owner, $unit);
        $this->closeDeviceRepair($this->owner, $unit, RepairStatus::CANCELLED);
        $this->assertSame(UnitStatus::INSPECTION, $unit->fresh()->status);
        $movements = InventoryMovement::count();

        $stale = fn (callable $action) => $this->assertStale($action, UnitStatus::INSPECTION, $openedRevision + 2);

        $stale(fn () => app(RecordInspection::class)($this->owner, $this->store, $unit->id, UnitStatus::INSPECTION, $openedRevision, InspectionOutcome::READY, UnitCondition::A, null, 'stale-inspect'));
        $stale(fn () => app(StartRepair::class)($this->owner, $this->store, $unit->id, UnitStatus::INSPECTION, $openedRevision, RepairerType::EXTERNAL, 'Shop', 'Again', 'stale-repair'));
        $stale(fn () => app(RemoveUnit::class)($this->owner, $this->store, $unit->id, AdjustmentReason::DAMAGED, 'Gone', 'stale-remove', $openedRevision));

        $this->assertSame($movements, InventoryMovement::count());
        $this->assertSame(UnitStatus::INSPECTION, $unit->fresh()->status);
    }

    #[Test]
    public function a_replay_returns_the_original_result_before_any_stale_check_and_a_reused_key_conflicts(): void
    {
        $unit = $this->deviceNeedingRepair($this->owner, $this->store, $this->usedPhone, self::imei('35693803564380'));
        $start = fn (string $repairer, string $key) => app(StartRepair::class)($this->owner, $this->store, $unit->id, UnitStatus::INSPECTION, 1, RepairerType::EXTERNAL, $repairer, 'Cracked', $key);

        $first = $start('Shop', 'start-key');
        $replay = $start('Shop', 'start-key');

        $this->assertSame($first->id, $replay->id);
        $this->assertFalse($replay->wasRecentlyCreated);
        $this->assertSame(1, DeviceRepair::count());

        $this->expectException(IdempotencyConflictException::class);
        $start('Other shop', 'start-key');
    }

    #[Test]
    public function a_late_failure_rolls_back_the_status_change_and_the_movement(): void
    {
        $unit = $this->deviceNeedingRepair($this->owner, $this->store, $this->usedPhone, self::imei('35693803564380'));
        $this->startDeviceRepair($this->owner, $unit);
        $movements = InventoryMovement::count();

        DeviceCostEntry::creating(fn () => throw new RuntimeException('Disk full'));

        try {
            $this->closeDeviceRepair($this->owner, $unit, costs: [DeviceCostInput::fromInput('repair_labor', null, '300.00', 'TRY', false)]);
            $this->fail('The late failure should propagate.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Disk full', $exception->getMessage());
        }

        $this->assertUnitState($unit, UnitStatus::IN_REPAIR, 2);
        $this->assertSame(RepairStatus::OPEN, DeviceRepair::query()->sole()->status);
        $this->assertSame($movements, InventoryMovement::count());
        $this->assertSame(0, DeviceCostEntry::count());
    }

    #[Test]
    public function a_service_only_owner_completes_a_repair_without_costs_and_cannot_send_any(): void
    {
        $this->revokeFromOwners('manage_device_costs');
        $unit = $this->deviceNeedingRepair($this->owner, $this->store, $this->usedPhone, self::imei('35693803564380'));
        $this->startDeviceRepair($this->owner, $unit);

        try {
            $this->closeDeviceRepair($this->owner, $unit, costs: [DeviceCostInput::fromInput('repair_labor', null, '300.00', 'TRY', false)]);
            $this->fail('Costs need cost access.');
        } catch (AuthorizationException) {
        }

        $this->closeDeviceRepair($this->owner, $unit);
        $this->assertUnitState($unit, UnitStatus::INSPECTION, 3);
        $this->assertSame(0, DeviceCostEntry::count());
    }

    #[Test]
    public function servicing_needs_the_service_permission_and_store_access(): void
    {
        $unit = $this->registerDevice($this->owner, $this->store, $this->usedPhone, self::imei('35693803564380'));
        $otherStore = Store::factory()->create(['code' => 'KT02']);

        try {
            app(RecordInspection::class)($this->owner, $otherStore, $unit->id, UnitStatus::AVAILABLE, 0, InspectionOutcome::NEEDS_REPAIR, null, 'x', 'k1');
            $this->fail('A unit from another store must not be found.');
        } catch (ModelNotFoundException) {
            $this->assertSame(UnitStatus::AVAILABLE, $unit->fresh()->status);
        }

        $this->revokeFromOwners('service_devices');
        $this->expectException(AuthorizationException::class);
        $this->inspectDevice($this->owner, $unit, InspectionOutcome::NEEDS_REPAIR);
    }

    private function assertUnitState(InventoryUnit $unit, UnitStatus $status, int $revision, ?UnitCondition $condition = null): void
    {
        $fresh = $unit->fresh();
        $this->assertSame($status, $fresh->status);
        $this->assertSame($revision, $fresh->lifecycle_revision);

        if ($condition !== null) {
            $this->assertSame($condition, $fresh->condition);
        }
    }

    private function availableUnits(): int
    {
        return InventoryUnit::query()->where('status', UnitStatus::AVAILABLE)->count();
    }

    private function assertValidationErrorOn(string $key, callable $action): void
    {
        try {
            $action();
            $this->fail("Expected a validation error on {$key}.");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($key, $exception->errors());
        }
    }

    private function assertStale(callable $action, UnitStatus $currentStatus, int $currentRevision): void
    {
        try {
            $action();
            $this->fail('Expected a stale form.');
        } catch (StaleUnitStateException $exception) {
            $this->assertSame($currentStatus, $exception->currentStatus);
            $this->assertSame($currentRevision, $exception->currentRevision);
        }
    }
}
