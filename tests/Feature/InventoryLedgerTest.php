<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Catalog\SaveProductDefinition;
use App\Actions\Inventory\RecordOpeningBalance;
use App\Actions\Inventory\RecordStockCount;
use App\Actions\Inventory\RegisterOpeningUnit;
use App\Actions\Inventory\RemoveUnit;
use App\Enums\AdjustmentReason;
use App\Enums\IdentifierType;
use App\Enums\MovementType;
use App\Enums\TrackingMode;
use App\Enums\UnitCondition;
use App\Enums\UnitStatus;
use App\Exceptions\Inventory\IdempotencyConflictException;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\InventoryUnitIdentifier;
use App\Models\ProductDefinition;
use App\Models\Store;
use App\Models\User;
use App\Rules\ValidImei;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Support\InteractsWithOperationalInventory;
use Tests\TestCase;

class InventoryLedgerTest extends TestCase
{
    use InteractsWithOperationalInventory;
    use RefreshDatabase;

    private User $owner;

    private Store $storeOne;

    private Store $storeTwo;

    private ProductDefinition $cable;

    private ProductDefinition $usedPhone;

    private ProductDefinition $newPhone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOperationalRoles();
        $this->owner = $this->createOwner();
        $this->storeOne = Store::factory()->create(['code' => 'KT01']);
        $this->storeTwo = Store::factory()->create(['code' => 'KT02']);
        $this->cable = ProductDefinition::factory()->create(['name' => 'USB-C cable', 'tracking_mode' => TrackingMode::QUANTITY]);
        $this->usedPhone = ProductDefinition::factory()->create(['name' => 'iPhone 13 (used)', 'tracking_mode' => TrackingMode::USED_DEVICE]);
        $this->newPhone = ProductDefinition::factory()->create(['name' => 'Galaxy A55', 'tracking_mode' => TrackingMode::SERIALIZED]);
    }

    #[Test]
    public function creating_a_definition_creates_no_stock(): void
    {
        app(SaveProductDefinition::class)($this->owner, null, ['name' => 'Charger', 'tracking_mode' => 'quantity']);

        $this->assertSame(0, InventoryBalance::count());
        $this->assertSame(0, InventoryUnit::count());
        $this->assertSame(0, InventoryMovement::count());
    }

    #[Test]
    public function opening_balance_is_recorded_once_with_a_complete_movement(): void
    {
        $movement = $this->openingBalance($this->storeOne, 10, 'key-1', 'Initial count');

        $this->assertSame(10, $this->quantity($this->storeOne));
        $this->assertSame(MovementType::OPENING_BALANCE, $movement->type);
        $this->assertSame(AdjustmentReason::OPENING_BALANCE, $movement->reason);
        $this->assertSame(10, $movement->quantity_change);
        $this->assertSame(10, $movement->quantity_after);
        $this->assertSame($this->owner->id, $movement->user_id);
        $this->assertSame($this->storeOne->id, $movement->store_id);
        $this->assertSame($this->cable->id, $movement->product_definition_id);
        $this->assertSame('Initial count', $movement->note);
        $this->assertNotNull($movement->occurred_at);

        $this->assertValidationError('quantity', fn () => $this->openingBalance($this->storeOne, 5, 'key-2'));

        $this->assertSame(10, $this->quantity($this->storeOne));
        $this->assertSame(1, InventoryMovement::count());

        $this->openingBalance($this->storeTwo, 4, 'key-3');
        $this->assertSame(4, $this->quantity($this->storeTwo));
    }

    #[Test]
    public function opening_balance_is_rejected_once_any_history_exists(): void
    {
        $this->recordCount($this->storeOne, 2, 'count-1');

        $this->assertValidationError('quantity', fn () => $this->openingBalance($this->storeOne, 10, 'open-1'));

        $this->assertSame(2, $this->quantity($this->storeOne));
        $this->assertSame(1, InventoryMovement::count());
    }

    #[Test]
    public function the_database_allows_only_one_opening_balance_per_store_and_definition(): void
    {
        $this->openingBalance($this->storeOne, 10, 'key-1');

        $this->expectException(UniqueConstraintViolationException::class);

        InventoryMovement::query()->create([
            'store_id' => $this->storeOne->id,
            'product_definition_id' => $this->cable->id,
            'type' => MovementType::OPENING_BALANCE,
            'reason' => AdjustmentReason::OPENING_BALANCE,
            'quantity_change' => 3,
            'quantity_after' => 3,
            'user_id' => $this->owner->id,
            'idempotency_key' => 'bypass',
            'request_fingerprint' => str_repeat('0', 64),
            'occurred_at' => now(),
        ]);
    }

    #[Test]
    public function counts_validate_the_reason_the_total_and_the_tracking_mode(): void
    {
        $this->openingBalance($this->storeOne, 10, 'open');

        $this->assertValidationError('note', fn () => $this->recordCount($this->storeOne, 9, 'a', '   '));
        $this->assertValidationError('note', fn () => $this->recordCount($this->storeOne, 9, 'b', null));
        $this->assertValidationError('counted_quantity', fn () => $this->recordCount($this->storeOne, -1, 'c'));
        $this->assertValidationError('counted_quantity', fn () => $this->recordCount($this->storeOne, RecordStockCount::MAX_QUANTITY + 1, 'd'));
        $this->assertValidationError('counted_quantity', fn () => app(RecordStockCount::class)(
            $this->owner, $this->storeOne, $this->usedPhone, 1, null, 'Shelf count', 'e',
        ));

        $this->assertSame(10, $this->quantity($this->storeOne));
        $this->assertSame(1, InventoryMovement::count());
    }

    #[Test]
    public function a_failure_after_the_stock_change_rolls_back_the_whole_operation(): void
    {
        $this->openingBalance($this->storeOne, 10, 'open');

        InventoryMovement::creating(function (InventoryMovement $movement): void {
            if ($movement->idempotency_key === 'fails') {
                throw new RuntimeException('Simulated failure while writing the movement.');
            }
        });

        $this->assertThrows(
            fn () => $this->recordCount($this->storeOne, 6, 'fails', 'Broken'),
            RuntimeException::class,
        );

        $this->assertSame(10, $this->quantity($this->storeOne));
        $this->assertSame(1, InventoryMovement::count());

        $this->assertThrows(
            fn () => $this->registerOpeningUnit($this->storeOne, $this->usedPhone, self::imei('35693803564380'), key: 'fails'),
            RuntimeException::class,
        );

        $this->assertSame(0, InventoryUnit::count());
        $this->assertSame(0, InventoryUnitIdentifier::count());
    }

    #[Test]
    public function an_identical_retry_returns_the_original_movement_without_reapplying_it(): void
    {
        $opening = $this->openingBalance($this->storeOne, 10, 'open');

        $first = $this->recordCount($this->storeOne, 7, 'retry-key', 'Damaged box', $opening->id);
        $retry = $this->recordCount($this->storeOne, 7, 'retry-key', '  Damaged box  ', $opening->id);

        $this->assertTrue($first->wasRecentlyCreated);
        $this->assertFalse($retry->wasRecentlyCreated);
        $this->assertSame($first->id, $retry->id);
        $this->assertSame(7, $this->quantity($this->storeOne));
        $this->assertSame(2, InventoryMovement::count());
    }

    #[Test]
    public function reusing_a_key_for_a_different_request_or_actor_is_rejected(): void
    {
        $opening = $this->openingBalance($this->storeOne, 10, 'open');
        $this->recordCount($this->storeOne, 7, 'shared', 'Damaged', $opening->id);

        $conflicts = [
            'different total' => fn () => $this->recordCount($this->storeOne, 8, 'shared', 'Damaged', $opening->id),
            'different reason' => fn () => $this->recordCount($this->storeOne, 7, 'shared', 'Lost', $opening->id),
            'different count start' => fn () => $this->recordCount($this->storeOne, 7, 'shared', 'Damaged', null),
            'different store' => fn () => $this->recordCount($this->storeTwo, 7, 'shared', 'Damaged', $opening->id),
            'different operation' => fn () => $this->openingBalance($this->storeTwo, 3, 'shared'),
            'different target' => fn () => app(RecordStockCount::class)(
                $this->owner, $this->storeOne, ProductDefinition::factory()->create(), 7, $opening->id, 'Damaged', 'shared',
            ),
            'different actor' => fn () => app(RecordStockCount::class)(
                $this->createOwner(), $this->storeOne, $this->cable, 7, $opening->id, 'Damaged', 'shared',
            ),
        ];

        foreach ($conflicts as $case => $attempt) {
            try {
                $attempt();
                $this->fail("Expected an idempotency conflict for: {$case}");
            } catch (IdempotencyConflictException) {
                // Expected.
            }
        }

        $this->assertSame(7, $this->quantity($this->storeOne));
        $this->assertSame(0, $this->quantity($this->storeTwo));
        $this->assertSame(2, InventoryMovement::count());
    }

    #[Test]
    public function a_stale_form_cannot_record_a_second_opening_balance_or_a_second_count(): void
    {
        // Two forms rendered while the store had no history and 10 on hand.
        $opening = $this->openingBalance($this->storeOne, 10, 'form-a');

        $this->assertValidationError('quantity', fn () => $this->openingBalance($this->storeOne, 10, 'form-b'));

        $this->recordCount($this->storeOne, 4, 'tab-1', 'Damaged', $opening->id);

        // A second tab started counting from the same stock state.
        $this->assertValidationError('count', fn () => $this->recordCount($this->storeOne, 5, 'tab-2', 'Lost', $opening->id));

        $this->assertSame(4, $this->quantity($this->storeOne));
        $this->assertSame(2, InventoryMovement::count());
    }

    #[Test]
    public function inventory_actions_require_permission_and_store_access(): void
    {
        $employee = User::factory()->create()->assignRole('employee');
        $this->storeOne->employees()->attach($employee);

        $this->assertThrows(fn () => app(RecordOpeningBalance::class)($employee, $this->storeOne, $this->cable, 5, null, 'e-1'), AuthorizationException::class);

        $this->revokeFromOwners('adjust_inventory');

        $this->assertThrows(fn () => $this->openingBalance($this->storeOne, 5, 'o-1'), AuthorizationException::class);
        $this->assertSame(0, InventoryMovement::count());
    }

    #[Test]
    public function used_device_is_registered_with_condition_and_identifiers(): void
    {
        $imei = self::imei('35693803564380');
        $secondImei = self::imei('35693803564381');

        $movement = $this->registerOpeningUnit($this->storeOne, $this->usedPhone, $imei, $secondImei, ' SN-001 ', UnitCondition::B, key: 'reg');
        $unit = $movement->unit;

        $this->assertSame(UnitStatus::AVAILABLE, $unit->status);
        $this->assertSame(UnitCondition::B, $unit->condition);
        $this->assertSame($this->storeOne->id, $unit->store_id);
        $this->assertSame(
            [['imei', 1, $imei], ['imei', 2, $secondImei], ['serial', 1, 'SN-001']],
            $unit->identifiers->map(fn ($identifier) => [$identifier->type->value, $identifier->position, $identifier->value])->all(),
        );
        $this->assertSame(MovementType::OPENING_BALANCE, $movement->type);
        $this->assertSame(1, $movement->quantity_change);
        $this->assertSame(UnitStatus::AVAILABLE, $movement->unit_status_after);
    }

    #[Test]
    public function unit_identifiers_and_condition_are_validated(): void
    {
        $this->assertTrue(ValidImei::isValid('490154203237518'));
        $this->assertFalse(ValidImei::isValid('490154203237519'));

        $this->assertValidationError('imei', fn () => $this->registerOpeningUnit($this->storeOne, $this->usedPhone, null));
        $this->assertValidationError('imei', fn () => $this->registerOpeningUnit($this->storeOne, $this->usedPhone, '490154203237519'));
        $this->assertValidationError('imei', fn () => $this->registerOpeningUnit($this->storeOne, $this->usedPhone, '49015420323751'));
        $this->assertValidationError('imei', fn () => $this->registerOpeningUnit($this->storeOne, $this->usedPhone, null, '490154203237518'));
        $this->assertValidationError('secondary_imei', fn () => $this->registerOpeningUnit($this->storeOne, $this->usedPhone, '490154203237518', '490154203237518'));
        $this->assertValidationError('condition', fn () => $this->registerOpeningUnit($this->storeOne, $this->usedPhone, '490154203237518', condition: UnitCondition::NEW));
        $this->assertValidationError('condition', fn () => $this->registerOpeningUnit($this->storeOne, $this->newPhone, '490154203237518', condition: UnitCondition::A));
        $this->assertValidationError('condition', fn () => $this->registerOpeningUnit($this->storeOne, $this->cable, '490154203237518', condition: UnitCondition::NEW));

        $this->assertSame(0, InventoryUnit::count());

        $this->registerOpeningUnit($this->storeOne, $this->newPhone, null, null, 'SN-ONLY', UnitCondition::NEW);
        $this->assertSame(1, InventoryUnit::count());
    }

    #[Test]
    public function imei_is_unique_system_wide_including_removed_units_and_secondary_imeis(): void
    {
        $imei = self::imei('35693803564380');
        $secondImei = self::imei('35693803564381');
        $unit = $this->registerOpeningUnit($this->storeOne, $this->usedPhone, $imei, $secondImei, key: 'reg-1')->unit;

        $this->assertValidationError('imei', fn () => $this->registerOpeningUnit($this->storeTwo, $this->usedPhone, $imei, key: 'reg-2'));
        $this->assertValidationError('imei', fn () => $this->registerOpeningUnit($this->storeTwo, $this->newPhone, $secondImei, condition: UnitCondition::NEW, key: 'reg-3'));
        $this->assertValidationError('secondary_imei', fn () => $this->registerOpeningUnit($this->storeTwo, $this->usedPhone, self::imei('11111111111111'), $imei, key: 'reg-4'));

        app(RemoveUnit::class)($this->owner, $this->storeOne, $unit->id, AdjustmentReason::DAMAGED, 'Screen broken', 'remove-1');

        $this->assertValidationError('imei', fn () => $this->registerOpeningUnit($this->storeOne, $this->usedPhone, $imei, key: 'reg-5'));
        $this->assertSame(1, InventoryUnit::count());
    }

    #[Test]
    public function serial_is_unique_per_definition_including_removed_units(): void
    {
        $unit = $this->registerOpeningUnit($this->storeOne, $this->newPhone, null, null, 'SN-42', UnitCondition::NEW, key: 'a')->unit;

        $this->assertValidationError('serial', fn () => $this->registerOpeningUnit($this->storeTwo, $this->newPhone, null, null, ' SN-42 ', UnitCondition::NEW, key: 'b'));
        $this->assertValidationError('serial', fn () => $this->registerOpeningUnit($this->storeTwo, $this->newPhone, null, null, 'sn-42', UnitCondition::NEW, key: 'b2'), expectFailure: false);

        app(RemoveUnit::class)($this->owner, $this->storeOne, $unit->id, AdjustmentReason::LOST, 'Missing', 'remove');

        $this->assertValidationError('serial', fn () => $this->registerOpeningUnit($this->storeOne, $this->newPhone, null, null, 'SN-42', UnitCondition::NEW, key: 'c'));

        $this->registerOpeningUnit($this->storeOne, $this->usedPhone, null, null, 'SN-42', UnitCondition::C, key: 'd');
        $this->assertSame(3, InventoryUnit::count());
    }

    #[Test]
    public function the_database_enforces_identifier_uniqueness(): void
    {
        $imei = self::imei('35693803564380');
        $first = $this->registerOpeningUnit($this->storeOne, $this->usedPhone, $imei, null, 'SN-1', key: 'a')->unit;
        $second = $this->registerOpeningUnit($this->storeTwo, $this->usedPhone, self::imei('35693803564381'), key: 'b')->unit;

        $duplicates = [
            'imei reused as secondary on another unit' => [$second, IdentifierType::IMEI, 2, $imei, $this->usedPhone],
            'serial reused within the definition' => [$second, IdentifierType::SERIAL, 1, 'SN-1', $this->usedPhone],
        ];

        foreach ($duplicates as $case => [$unit, $type, $position, $value, $definition]) {
            try {
                InventoryUnitIdentifier::query()->create([
                    'inventory_unit_id' => $unit->id,
                    'product_definition_id' => $definition->id,
                    'type' => $type,
                    'position' => $position,
                    'value' => $value,
                ]);
                $this->fail("Expected a unique violation for: {$case}");
            } catch (UniqueConstraintViolationException) {
                // Expected.
            }
        }

        $this->expectException(QueryException::class);

        InventoryUnitIdentifier::query()->create([
            'inventory_unit_id' => $first->id,
            'product_definition_id' => $this->newPhone->id,
            'type' => IdentifierType::SERIAL,
            'position' => 2,
            'value' => 'SN-MISMATCH',
        ]);
    }

    #[Test]
    public function removing_a_unit_preserves_it_and_records_the_status_change(): void
    {
        $unit = $this->registerOpeningUnit($this->storeOne, $this->usedPhone, self::imei('35693803564380'), key: 'reg')->unit;

        $this->assertValidationError('note', fn () => app(RemoveUnit::class)($this->owner, $this->storeOne, $unit->id, AdjustmentReason::DAMAGED, ' ', 'r-0'));
        $this->assertValidationError('reason', fn () => app(RemoveUnit::class)($this->owner, $this->storeOne, $unit->id, AdjustmentReason::FOUND, 'note', 'r-1'));

        $movement = app(RemoveUnit::class)($this->owner, $this->storeOne, $unit->id, AdjustmentReason::DAMAGED, 'Cracked board', 'r-2');

        $unit->refresh();
        $this->assertSame(UnitStatus::REMOVED, $unit->status);
        $this->assertNotNull($unit->removed_at);
        $this->assertSame(1, $unit->identifiers()->count());
        $this->assertSame(MovementType::ADJUSTMENT_DECREASE, $movement->type);
        $this->assertSame(-1, $movement->quantity_change);
        $this->assertSame(UnitStatus::AVAILABLE, $movement->unit_status_before);
        $this->assertSame(UnitStatus::REMOVED, $movement->unit_status_after);

        $this->assertSame($movement->id, app(RemoveUnit::class)($this->owner, $this->storeOne, $unit->id, AdjustmentReason::DAMAGED, 'Cracked board', 'r-2')->id);
        $this->assertValidationError('reason', fn () => app(RemoveUnit::class)($this->owner, $this->storeOne, $unit->id, AdjustmentReason::DAMAGED, 'Again', 'r-3'));
        $this->assertSame(2, InventoryMovement::count());
    }

    #[Test]
    public function units_are_only_reachable_through_their_own_store(): void
    {
        $unit = $this->registerOpeningUnit($this->storeOne, $this->usedPhone, self::imei('35693803564380'), key: 'reg')->unit;

        $this->assertThrows(
            fn () => app(RemoveUnit::class)($this->owner, $this->storeTwo, $unit->id, AdjustmentReason::DAMAGED, 'note', 'cross'),
            ModelNotFoundException::class,
        );

        $this->assertSame(UnitStatus::AVAILABLE, $unit->fresh()->status);
    }

    #[Test]
    public function tracking_mode_is_locked_once_any_store_has_history(): void
    {
        $save = app(SaveProductDefinition::class);

        $save($this->owner, $this->cable, ['name' => 'USB-C cable', 'tracking_mode' => 'serialized']);
        $this->assertSame(TrackingMode::SERIALIZED, $this->cable->fresh()->tracking_mode);

        $save($this->owner, $this->cable->fresh(), ['name' => 'USB-C cable', 'tracking_mode' => 'quantity']);
        $this->openingBalance($this->storeTwo, 1, 'open');

        $this->assertValidationError('tracking_mode', fn () => $save($this->owner, $this->cable->fresh(), ['name' => 'USB-C cable', 'tracking_mode' => 'serialized']));
        $this->assertSame(TrackingMode::QUANTITY, $this->cable->fresh()->tracking_mode);

        $save($this->owner, $this->cable->fresh(), ['name' => 'USB-C cable 1m', 'tracking_mode' => 'quantity']);
        $this->assertSame('USB-C cable 1m', $this->cable->fresh()->name);
    }

    #[Test]
    public function a_stock_action_rechecks_the_tracking_mode_under_the_lock(): void
    {
        // A form rendered while the definition was quantity-tracked, submitted after the mode changed.
        $staleDefinition = $this->cable->fresh();
        $this->cable->update(['tracking_mode' => TrackingMode::USED_DEVICE]);

        $this->assertValidationError('quantity', fn () => app(RecordOpeningBalance::class)($this->owner, $this->storeOne, $staleDefinition, 5, null, 'stale'));
        $this->assertSame(0, InventoryMovement::count());
    }

    #[Test]
    public function movements_are_append_only(): void
    {
        $movement = $this->openingBalance($this->storeOne, 10, 'open');

        $this->assertThrows(fn () => $movement->update(['note' => 'changed']), LogicException::class);
        $this->assertThrows(fn () => $movement->delete(), LogicException::class);
        $this->assertSame(1, InventoryMovement::count());
    }

    private function openingBalance(Store $store, int $quantity, string $key, ?string $note = null): InventoryMovement
    {
        return app(RecordOpeningBalance::class)($this->owner, $store, $this->cable, $quantity, $note, $key);
    }

    /**
     * Record a count that started from the current stock state unless a start is given.
     */
    private function recordCount(Store $store, int $counted, string $key, ?string $reason = 'Shelf count', int|false|null $startedAfter = false): ?InventoryMovement
    {
        $startedAfter = $startedAfter === false ? RecordStockCount::latestMovementId($store, $this->cable) : $startedAfter;

        return app(RecordStockCount::class)($this->owner, $store, $this->cable, $counted, $startedAfter, $reason, $key);
    }

    private function registerOpeningUnit(
        Store $store,
        ProductDefinition $definition,
        ?string $imei,
        ?string $secondaryImei = null,
        ?string $serial = null,
        UnitCondition $condition = UnitCondition::B,
        ?string $key = null,
    ): InventoryMovement {
        return app(RegisterOpeningUnit::class)($this->owner, $store, $definition, $condition, $imei, $secondaryImei, $serial, null, $key ?? uniqid('reg-', true));
    }

    private function quantity(Store $store): int
    {
        return (int) InventoryBalance::query()
            ->where('store_id', $store->id)
            ->where('product_definition_id', $this->cable->id)
            ->value('quantity');
    }

    private function assertValidationError(string $key, callable $attempt, bool $expectFailure = true): void
    {
        try {
            $attempt();
        } catch (ValidationException $exception) {
            $this->assertTrue($expectFailure, "Unexpected validation error: {$exception->getMessage()}");
            $this->assertArrayHasKey($key, $exception->errors());

            return;
        }

        $this->assertFalse($expectFailure, "Expected a validation error on [{$key}].");
    }
}
