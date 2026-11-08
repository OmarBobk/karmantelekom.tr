<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Inventory\ApproveUnitReview;
use App\Actions\Inventory\CompleteInventorySetup;
use App\Actions\Inventory\LocateDeviceForReconciliation;
use App\Actions\Inventory\MarkUnitFound;
use App\Actions\Inventory\RecordFoundUnit;
use App\Actions\Inventory\RecordOpeningBalance;
use App\Actions\Inventory\RemoveUnit;
use App\Actions\Pos\ResolveCounterCode;
use App\Enums\AdjustmentReason;
use App\Enums\MovementType;
use App\Enums\TrackingMode;
use App\Enums\UnitCondition;
use App\Enums\UnitStatus;
use App\Exceptions\Inventory\IdempotencyConflictException;
use App\Exceptions\Inventory\StaleUnitStateException;
use App\Livewire\Backend\Inventory\ProductInventory;
use App\Livewire\Backend\Inventory\StoreInventory;
use App\Livewire\Backend\Inventory\UnitDetail;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\ProductDefinition;
use App\Models\Store;
use App\Models\User;
use App\Services\Inventory\ReconciliationRollbackGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\InteractsWithDeviceLifecycle;
use Tests\Support\InteractsWithOperationalInventory;
use Tests\TestCase;

class DeviceReconciliationTest extends TestCase
{
    use InteractsWithDeviceLifecycle;
    use InteractsWithOperationalInventory;
    use RefreshDatabase;

    private User $owner;

    private Store $store;

    private Store $otherStore;

    private ProductDefinition $usedPhone;

    private ProductDefinition $sealedPhone;

    private ProductDefinition $cable;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOperationalRoles();
        $this->useEnglishLocale();
        $this->owner = $this->createOwner();
        $this->store = Store::factory()->create(['code' => 'KT01']);
        $this->otherStore = Store::factory()->create(['code' => 'KT02']);
        $this->usedPhone = ProductDefinition::factory()->create(['name' => 'iPhone 13', 'tracking_mode' => TrackingMode::USED_DEVICE]);
        $this->sealedPhone = ProductDefinition::factory()->create(['name' => 'Galaxy A55', 'tracking_mode' => TrackingMode::SERIALIZED]);
        $this->cable = ProductDefinition::factory()->create(['name' => 'USB-C cable', 'tracking_mode' => TrackingMode::QUANTITY]);
    }

    #[Test]
    public function completing_initial_setup_closes_opening_stock_without_touching_history(): void
    {
        $this->registerDevice($this->owner, $this->store, $this->usedPhone, self::imei('35693803564380'));
        app(RecordOpeningBalance::class)($this->owner, $this->store, $this->cable, 5, null, (string) Str::uuid());
        $history = InventoryMovement::query()->orderBy('id')->get()->toArray();

        $this->assertTrue($this->store->inventorySetupIsOpen());
        $completed = app(CompleteInventorySetup::class)($this->owner, $this->store);
        $completedAt = $completed->inventory_setup_completed_at;
        $this->assertSame($this->owner->id, $completed->inventory_setup_completed_by);

        $this->travel(1)->hour();
        $again = app(CompleteInventorySetup::class)($this->createOwner(), $this->store->fresh());
        $this->assertTrue($again->inventory_setup_completed_at->equalTo($completedAt));
        $this->assertSame($this->owner->id, $again->inventory_setup_completed_by);

        $this->assertValidationErrorOn('setup', fn () => $this->registerDevice($this->owner, $this->store, $this->usedPhone, self::imei('35693803564381')));
        $this->assertValidationErrorOn('setup', fn () => app(RecordOpeningBalance::class)($this->owner, $this->store, ProductDefinition::factory()->create(['tracking_mode' => TrackingMode::QUANTITY]), 2, null, (string) Str::uuid()));
        $this->assertSame($history, InventoryMovement::query()->orderBy('id')->get()->toArray());

        $this->assertTrue($this->otherStore->fresh()->inventorySetupIsOpen());
        $this->registerDevice($this->owner, $this->otherStore, $this->usedPhone, self::imei('35693803564382'));
    }

    #[Test]
    public function only_an_owner_with_adjustment_permission_completes_setup_from_the_branch_page(): void
    {
        $employee = User::factory()->create()->assignRole('employee');
        $this->assertRefused(fn () => app(CompleteInventorySetup::class)($employee, $this->store));

        $this->revokeFromOwners('adjust_inventory');
        $this->assertRefused(fn () => app(CompleteInventorySetup::class)($this->owner, $this->store));
        $this->assertTrue($this->store->fresh()->inventorySetupIsOpen());

        Role::findByName('admin')->givePermissionTo('adjust_inventory');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Livewire::actingAs($this->owner)->test(StoreInventory::class, ['store' => $this->store])
            ->assertSee('Complete setup')
            ->call('completeSetup')
            ->assertSee('Completed')
            ->assertDontSee('Complete setup</button>', false);

        $this->assertFalse($this->store->fresh()->inventorySetupIsOpen());

        Livewire::actingAs($this->owner)->test(ProductInventory::class, ['store' => $this->store, 'productDefinition' => $this->usedPhone])
            ->assertDontSee('Opening stock: register device')
            ->assertSee('Reconcile a device');
    }

    #[Test]
    public function a_missing_device_is_removed_and_a_found_one_returns_for_review_by_imei(): void
    {
        $imei = self::imei('35693803564380');
        $unit = $this->registerDevice($this->owner, $this->store, $this->usedPhone, $imei);
        $this->completeSetup();

        $located = app(LocateDeviceForReconciliation::class)($this->store, $this->usedPhone, $imei);
        $this->assertSame(LocateDeviceForReconciliation::UNIT, $located['outcome']);
        $this->assertSame($unit->id, $located['unit']->id);

        $missing = app(RemoveUnit::class)($this->owner, $this->store, $unit->id, AdjustmentReason::LOST, 'Not on shelf at count', (string) Str::uuid(), 0);
        $this->assertSame(-1, $missing->quantity_change);
        $this->assertSame(UnitStatus::REMOVED, $unit->fresh()->status);

        $found = app(MarkUnitFound::class)($this->owner, $this->store, $unit->id, 1, 'Found behind the counter', (string) Str::uuid());
        $this->assertSame(MovementType::ADJUSTMENT_INCREASE, $found->type);
        $this->assertSame(AdjustmentReason::FOUND, $found->reason);
        $this->assertSame(1, $found->quantity_change);
        $this->assertSame(UnitStatus::REMOVED, $found->unit_status_before);
        $this->assertSame(UnitStatus::REVIEW, $found->unit_status_after);
        $this->assertNull($unit->fresh()->removed_at);
        $this->assertDeviceUnsellable($imei);

        $approved = app(ApproveUnitReview::class)($this->owner, $this->store, $unit->id, 2, 'Matched the opening count sheet', (string) Str::uuid());
        $this->assertSame(MovementType::REVIEW_APPROVED, $approved->type);
        $this->assertSame(0, $approved->quantity_change);
        $this->assertSame(UnitStatus::AVAILABLE, $unit->fresh()->status);
        $this->assertSame(3, $unit->fresh()->lifecycle_revision);
        $this->assertSame(1, (int) InventoryMovement::query()->where('inventory_unit_id', $unit->id)->sum('quantity_change'));
    }

    #[Test]
    public function an_unidentified_device_is_recorded_by_serial_for_review_without_adding_sellable_stock(): void
    {
        $this->completeSetup();

        $located = app(LocateDeviceForReconciliation::class)($this->store, $this->sealedPhone, 'SN-A55-0001');
        $this->assertSame(LocateDeviceForReconciliation::NOT_FOUND, $located['outcome']);
        $this->assertSame('SN-A55-0001', $located['serial']);

        $this->assertValidationErrorOn('note', fn () => $this->recordFound($this->sealedPhone, null, 'SN-A55-0001', '  '));

        $movement = $this->recordFound($this->sealedPhone, null, 'SN-A55-0001');
        $unit = InventoryUnit::query()->findOrFail($movement->inventory_unit_id);
        $this->assertSame(UnitStatus::REVIEW, $unit->status);
        $this->assertSame(1, $movement->quantity_change);
        $this->assertNull($movement->unit_status_before);
        $this->assertSame(0, InventoryUnit::query()->where('status', UnitStatus::AVAILABLE)->count());
        $this->assertDeviceUnsellable('SN-A55-0001');

        $this->actingAs($this->owner)->get(route('subdomain.inventory.unit', [$this->store, $unit]))
            ->assertOk()
            ->assertSee(['Awaiting review', 'Found during reconciliation — source unverified, cost unknown', 'not sellable', 'Approve as sellable']);

        app(RemoveUnit::class)($this->owner, $this->store, $unit->id, AdjustmentReason::DAMAGED, 'Not ours, returned', (string) Str::uuid(), 0);
        $this->assertSame(UnitStatus::REMOVED, $unit->fresh()->status);
        $this->assertSame(0, (int) InventoryMovement::query()->where('inventory_unit_id', $unit->id)->sum('quantity_change'));
    }

    #[Test]
    public function reconciliation_refuses_stale_revisions(): void
    {
        $unit = $this->registerDevice($this->owner, $this->store, $this->usedPhone, self::imei('35693803564380'));
        app(RemoveUnit::class)($this->owner, $this->store, $unit->id, AdjustmentReason::LOST, 'Missing', (string) Str::uuid(), 0);

        $this->assertStale(fn () => app(MarkUnitFound::class)($this->owner, $this->store, $unit->id, 0, 'Found', (string) Str::uuid()));
        app(MarkUnitFound::class)($this->owner, $this->store, $unit->id, 1, 'Found', (string) Str::uuid());

        $this->assertStale(fn () => app(ApproveUnitReview::class)($this->owner, $this->store, $unit->id, 1, 'Verified', (string) Str::uuid()));
        $this->assertStale(fn () => app(RemoveUnit::class)($this->owner, $this->store, $unit->id, AdjustmentReason::LOST, 'Missing', (string) Str::uuid(), 1));
        $this->assertSame(UnitStatus::REVIEW, $unit->fresh()->status);
        $this->assertSame(2, $unit->fresh()->lifecycle_revision);
    }

    #[Test]
    public function the_reconcile_panel_reports_a_device_that_changed_after_lookup(): void
    {
        $imei = self::imei('35693803564380');
        $unit = $this->registerDevice($this->owner, $this->store, $this->usedPhone, $imei);

        $page = Livewire::actingAs($this->owner)->test(ProductInventory::class, ['store' => $this->store, 'productDefinition' => $this->usedPhone])
            ->set('reconcileCode', $imei)
            ->call('findDevice')
            ->assertSet('reconcileOutcome', LocateDeviceForReconciliation::UNIT)
            ->assertSee('Mark missing');

        app(RemoveUnit::class)($this->owner, $this->store, $unit->id, AdjustmentReason::DAMAGED, 'Cracked', (string) Str::uuid(), 0);

        $page->set('reconcileReason', 'Not on shelf')
            ->call('markMissing')
            ->assertHasErrors('reconcileReason')
            ->assertSee('This device changed after you looked it up');

        $this->assertSame(1, InventoryMovement::query()->where('inventory_unit_id', $unit->id)->where('type', MovementType::ADJUSTMENT_DECREASE)->count());
    }

    #[Test]
    public function duplicate_submissions_replay_and_changed_payloads_conflict(): void
    {
        $unit = $this->registerDevice($this->owner, $this->store, $this->usedPhone, self::imei('35693803564380'));
        app(RemoveUnit::class)($this->owner, $this->store, $unit->id, AdjustmentReason::LOST, 'Missing', (string) Str::uuid(), 0);

        $key = (string) Str::uuid();
        $first = app(MarkUnitFound::class)($this->owner, $this->store, $unit->id, 1, 'Found', $key);
        $replay = app(MarkUnitFound::class)($this->owner, $this->store, $unit->id, 1, 'Found', $key);
        $this->assertSame($first->id, $replay->id);

        $this->expectException(IdempotencyConflictException::class);
        try {
            app(MarkUnitFound::class)($this->owner, $this->store, $unit->id, 1, 'Found elsewhere', $key);
        } finally {
            $this->assertSame(1, InventoryMovement::query()->where('type', MovementType::ADJUSTMENT_INCREASE)->count());
            $this->assertSame(2, $unit->fresh()->lifecycle_revision);
        }
    }

    #[Test]
    public function an_identical_found_device_retry_creates_one_unit(): void
    {
        $key = (string) Str::uuid();
        $first = $this->recordFound($this->usedPhone, self::imei('35693803564380'), null, 'On shelf, no record', $key);
        $replay = $this->recordFound($this->usedPhone, self::imei('35693803564380'), null, 'On shelf, no record', $key);

        $this->assertSame($first->id, $replay->id);
        $this->assertSame(1, InventoryUnit::query()->count());
    }

    #[Test]
    public function reconciliation_stays_inside_the_branch(): void
    {
        $imei = self::imei('35693803564380');
        $elsewhere = $this->registerDevice($this->owner, $this->otherStore, $this->usedPhone, $imei);
        app(RemoveUnit::class)($this->owner, $this->otherStore, $elsewhere->id, AdjustmentReason::LOST, 'Missing', (string) Str::uuid(), 0);

        $located = app(LocateDeviceForReconciliation::class)($this->store, $this->usedPhone, $imei);
        $this->assertSame(LocateDeviceForReconciliation::OTHER_BRANCH, $located['outcome']);
        $this->assertNull($located['unit']);

        $this->expectModelNotFound(fn () => app(MarkUnitFound::class)($this->owner, $this->store, $elsewhere->id, 1, 'Found', (string) Str::uuid()));
        $this->assertValidationErrorOn('imei', fn () => $this->recordFound($this->usedPhone, $imei, null));
        $this->assertSame(0, $this->store->units()->count());

        $employee = User::factory()->create()->assignRole('employee');
        $this->assertRefused(fn () => app(MarkUnitFound::class)($employee, $this->otherStore, $elsewhere->id, 1, 'Found', (string) Str::uuid()));

        $this->revokeFromOwners('adjust_inventory');
        $this->assertRefused(fn () => $this->recordFound($this->usedPhone, self::imei('35693803564381'), null));
        $this->assertSame(UnitStatus::REMOVED, $elsewhere->fresh()->status);
    }

    #[Test]
    public function a_lookup_matching_several_branch_devices_requires_an_explicit_choice(): void
    {
        $code = self::imei('35693803564380');
        $byImei = $this->registerDevice($this->owner, $this->store, $this->usedPhone, $code);
        $bySerial = $this->registerDevice($this->owner, $this->store, $this->usedPhone, self::imei('35693803564381'), serial: $code);
        $elsewhere = $this->registerDevice($this->owner, $this->otherStore, $this->usedPhone, self::imei('35693803564382'));

        $page = Livewire::actingAs($this->owner)->test(ProductInventory::class, ['store' => $this->store, 'productDefinition' => $this->usedPhone])
            ->set('reconcileCode', $code)
            ->call('findDevice')
            ->assertSet('reconcileOutcome', LocateDeviceForReconciliation::AMBIGUOUS)
            ->assertSet('reconcileUnitId', null)
            ->assertSet('reconcileCandidateIds', [$byImei->id, $bySerial->id])
            ->assertSee('Choose this device')
            ->assertDontSee('Mark missing')
            ->assertDontSee(self::imei('35693803564382'))
            ->set('reconcileReason', 'Not on shelf')
            ->call('markMissing')
            ->call('chooseDevice', $elsewhere->id)
            ->assertSet('reconcileUnitId', null);

        $this->assertSame(0, InventoryMovement::query()->where('type', MovementType::ADJUSTMENT_DECREASE)->count());

        $page->call('chooseDevice', $bySerial->id)
            ->assertSet('reconcileOutcome', LocateDeviceForReconciliation::UNIT)
            ->assertSet('reconcileUnitId', $bySerial->id)
            ->set('reconcileReason', 'Not on shelf')
            ->call('markMissing')
            ->assertHasNoErrors();

        $this->assertSame(UnitStatus::REMOVED, $bySerial->fresh()->status);
        $this->assertSame(UnitStatus::AVAILABLE, $byImei->fresh()->status);
    }

    #[Test]
    public function rollback_is_refused_when_review_appears_only_in_movement_history(): void
    {
        $movement = $this->recordFound($this->usedPhone, self::imei('35693803564380'), null);
        app(RemoveUnit::class)($this->owner, $this->store, $movement->inventory_unit_id, AdjustmentReason::DAMAGED, 'Not ours', (string) Str::uuid(), 0);
        Store::query()->update(['inventory_setup_completed_at' => null]);

        $this->assertSame(0, InventoryUnit::query()->where('status', UnitStatus::REVIEW)->count());
        $this->assertSame(0, InventoryMovement::query()->where('type', MovementType::REVIEW_APPROVED)->count());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('inventory_movements (review status history): 2');

        app(ReconciliationRollbackGuard::class)->assertNoRecordedReconciliationData();
    }

    #[Test]
    public function an_owner_approves_a_review_device_from_its_page(): void
    {
        $movement = $this->recordFound($this->usedPhone, self::imei('35693803564380'), null);
        $unit = InventoryUnit::query()->findOrFail($movement->inventory_unit_id);

        Livewire::actingAs($this->owner)->test(UnitDetail::class, ['store' => $this->store, 'unit' => $unit])
            ->call('approveReview')
            ->assertHasErrors('reviewVerification')
            ->set('reviewVerification', 'Customer trade-in slip located')
            ->call('approveReview')
            ->assertHasNoErrors();

        $this->assertSame(UnitStatus::AVAILABLE, $unit->fresh()->status);
        $this->assertSame('Customer trade-in slip located', InventoryMovement::query()->latest('id')->first()->note);
    }

    private function completeSetup(): void
    {
        app(CompleteInventorySetup::class)($this->owner, $this->store);
    }

    private function recordFound(ProductDefinition $definition, ?string $imei, ?string $serial, ?string $reason = 'On shelf, no record', ?string $key = null): InventoryMovement
    {
        $condition = UnitCondition::allowedFor($definition->tracking_mode)[0];

        return app(RecordFoundUnit::class)($this->owner, $this->store, $definition, $condition, $imei, null, $serial, $reason, $key ?? (string) Str::uuid());
    }

    private function assertDeviceUnsellable(string $code): void
    {
        $matches = app(ResolveCounterCode::class)($this->owner, $this->store, $code);
        $this->assertNotEmpty($matches);
        $this->assertFalse(collect($matches)->contains('available', true));
    }

    private function assertStale(callable $action): void
    {
        try {
            $action();
            $this->fail('Expected a stale unit state.');
        } catch (StaleUnitStateException) {
            $this->addToAssertionCount(1);
        }
    }

    private function assertRefused(callable $action): void
    {
        try {
            $action();
            $this->fail('Expected the action to be refused.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }
    }

    private function expectModelNotFound(callable $action): void
    {
        try {
            $action();
            $this->fail('Expected the unit to be outside this branch.');
        } catch (ModelNotFoundException) {
            $this->addToAssertionCount(1);
        }
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
}
