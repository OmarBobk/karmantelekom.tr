<?php

use App\Services\Inventory\ReconciliationRollbackGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Zero-quantity unit events before this migration.
     *
     * @var list<string>
     */
    private const PREVIOUS_UNIT_EVENT_TYPES = [
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
     *
     * Approving a found device's review makes it sellable without changing owned stock,
     * so it is recorded as a zero-quantity unit event.
     */
    public function up(): void
    {
        $this->replaceConstraints([...self::PREVIOUS_UNIT_EVENT_TYPES, ReconciliationRollbackGuard::REVIEW_APPROVED_TYPE]);
    }

    /**
     * Reverse the migrations. Refused while any reconciliation data exists.
     */
    public function down(): void
    {
        app(ReconciliationRollbackGuard::class)->assertNoRecordedReconciliationData();

        $this->replaceConstraints(self::PREVIOUS_UNIT_EVENT_TYPES);
    }

    /**
     * @param  list<string>  $unitEventTypes
     */
    private function replaceConstraints(array $unitEventTypes): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $types = implode(', ', array_map(fn (string $type): string => "'{$type}'", $unitEventTypes));

        DB::statement('alter table inventory_movements drop constraint inventory_movements_unit_event_quantity_check');
        DB::statement('alter table inventory_movements drop constraint inventory_movements_quantity_change_check');
        DB::statement("alter table inventory_movements add constraint inventory_movements_quantity_change_check check (quantity_change <> 0 or (inventory_unit_id is not null and quantity_after is null and type in ({$types})))");
        DB::statement("alter table inventory_movements add constraint inventory_movements_unit_event_quantity_check check (type not in ({$types}) or quantity_change = 0)");
    }
};
