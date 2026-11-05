<?php

use App\Services\Inventory\DeviceLifecycleRollbackGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Unit lifecycle, cost, and identity events are recorded in the movement ledger with
     * a zero quantity. Every other movement must still change stock.
     *
     * @var list<string>
     */
    private const UNIT_EVENT_TYPES = [
        'inspection',
        'repair_started',
        'repair_completed',
        'repair_cancelled',
        'device_cost_recorded',
        'device_cost_reversed',
        'identifier_corrected',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $types = implode(', ', array_map(fn (string $type): string => "'{$type}'", self::UNIT_EVENT_TYPES));

        DB::statement('alter table inventory_movements drop constraint inventory_movements_quantity_change_check');
        DB::statement("alter table inventory_movements add constraint inventory_movements_quantity_change_check check (quantity_change <> 0 or (inventory_unit_id is not null and quantity_after is null and type in ({$types})))");
        DB::statement("alter table inventory_movements add constraint inventory_movements_unit_event_quantity_check check (type not in ({$types}) or quantity_change = 0)");
    }

    /**
     * Reverse the migrations. Refused before any constraint is dropped while unit events exist.
     */
    public function down(): void
    {
        app(DeviceLifecycleRollbackGuard::class)->assertNoRecordedLifecycleData();

        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement('alter table inventory_movements drop constraint inventory_movements_unit_event_quantity_check');
        DB::statement('alter table inventory_movements drop constraint inventory_movements_quantity_change_check');
        DB::statement('alter table inventory_movements add constraint inventory_movements_quantity_change_check check (quantity_change <> 0)');
    }
};
