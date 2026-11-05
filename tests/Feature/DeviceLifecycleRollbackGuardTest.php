<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TrackingMode;
use App\Exceptions\Inventory\LifecycleRollbackBlockedException;
use App\Models\ProductDefinition;
use App\Models\Store;
use App\Models\User;
use App\Services\Inventory\DeviceLifecycleRollbackGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\InteractsWithDeviceLifecycle;
use Tests\Support\InteractsWithOperationalInventory;
use Tests\TestCase;

class DeviceLifecycleRollbackGuardTest extends TestCase
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

    /**
     * @return array<string, array{0: string}>
     */
    public static function phaseFourMigrations(): array
    {
        return collect([
            '2026_11_05_180922_add_lifecycle_revision_to_inventory_units_table',
            '2026_11_05_180923_allow_unit_events_in_inventory_movements_table',
            '2026_11_05_180924_add_lookup_index_to_inventory_unit_identifiers_table',
            '2026_11_05_180925_create_device_inspections_table',
            '2026_11_05_180926_create_device_repairs_table',
            '2026_11_05_180927_create_device_cost_entries_table',
            '2026_11_05_180928_create_inventory_unit_identifier_corrections_table',
        ])->mapWithKeys(fn (string $migration): array => [$migration => [$migration]])->all();
    }

    #[Test]
    #[DataProvider('phaseFourMigrations')]
    public function rolling_back_any_phase_four_migration_with_lifecycle_data_is_refused_before_any_schema_change(string $migration): void
    {
        $unit = $this->deviceNeedingRepair($this->owner, $this->store, $this->usedPhone, self::imei('35693803564380'));
        $this->startDeviceRepair($this->owner, $unit);
        $schemaBefore = $this->phaseFourSchema();
        $this->assertSame([true, true, true, true], $schemaBefore['tables']);
        $this->assertTrue($schemaBefore['revision'] && $schemaBefore['lookup_index']);
        $this->assertContains('inventory_movements_unit_event_quantity_check', $schemaBefore['checks']);

        try {
            $this->migration($migration)->down();
            $this->fail('The rollback should have been refused.');
        } catch (LifecycleRollbackBlockedException $exception) {
            $this->assertSame(1, $exception->recordedData['device_repairs']);
            $this->assertSame(2, $exception->recordedData['inventory_movements (unit events)']);
            $this->assertStringContainsString('Nothing was changed', $exception->getMessage());
        }

        $this->assertSame($schemaBefore, $this->phaseFourSchema());
        $this->assertSame(1, DB::table('device_repairs')->count());
        $this->assertSame(1, DB::table('device_inspections')->count());
    }

    #[Test]
    public function stock_without_lifecycle_history_does_not_block_a_rollback(): void
    {
        $this->registerDevice($this->owner, $this->store, $this->usedPhone, self::imei('35693803564380'));

        $this->assertSame([], app(DeviceLifecycleRollbackGuard::class)->recordedLifecycleData());
    }

    #[Test]
    public function each_kind_of_lifecycle_data_blocks_a_rollback_on_its_own(): void
    {
        $unit = $this->registerDevice($this->owner, $this->store, $this->usedPhone, self::imei('35693803564380'));
        $guard = app(DeviceLifecycleRollbackGuard::class);

        DB::table('inventory_units')->where('id', $unit->id)->update(['lifecycle_revision' => 1]);
        $this->assertSame(['inventory_units (lifecycle revision above 0)' => 1], $guard->recordedLifecycleData());

        DB::table('inventory_units')->where('id', $unit->id)->update(['lifecycle_revision' => 0, 'status' => 'inspection']);
        $this->assertSame(['inventory_units (inspection or in repair)' => 1], $guard->recordedLifecycleData());
    }

    private function migration(string $name): Migration
    {
        return require database_path("migrations/{$name}.php");
    }

    /**
     * @return array{tables: list<bool>, revision: bool, lookup_index: bool, checks: list<string>}
     */
    private function phaseFourSchema(): array
    {
        return [
            'tables' => array_map(fn (string $table): bool => Schema::hasTable($table), DeviceLifecycleRollbackGuard::HISTORY_TABLES),
            'revision' => Schema::hasColumn('inventory_units', 'lifecycle_revision'),
            'lookup_index' => Schema::hasIndex('inventory_unit_identifiers', 'inventory_unit_identifiers_lookup_index'),
            'checks' => collect(DB::select("select constraint_name as name from information_schema.check_constraints where constraint_schema = database() and table_name = 'inventory_movements' order by constraint_name"))
                ->pluck('name')
                ->all(),
        ];
    }
}
