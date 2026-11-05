<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Exceptions\Inventory\LifecycleRollbackBlockedException;
use Illuminate\Support\Facades\DB;

/**
 * Refuses to roll back any Phase 4 migration while device lifecycle data exists.
 *
 * Every Phase 4 down() calls this first. A batch rollback runs the newest migration
 * first, so each one must check all Phase 4 data, not only its own table. It reads
 * the schema directly instead of through models because it runs inside migrations.
 */
class DeviceLifecycleRollbackGuard
{
    public const HISTORY_TABLES = [
        'device_inspections',
        'device_repairs',
        'device_cost_entries',
        'inventory_unit_identifier_corrections',
    ];

    public const UNIT_EVENT_TYPES = [
        'inspection',
        'repair_started',
        'repair_completed',
        'repair_cancelled',
        'device_cost_recorded',
        'device_cost_reversed',
        'identifier_corrected',
    ];

    public const LIFECYCLE_STATUSES = ['inspection', 'in_repair'];

    /**
     * @throws LifecycleRollbackBlockedException
     */
    public function assertNoRecordedLifecycleData(): void
    {
        $recordedData = $this->recordedLifecycleData();

        if ($recordedData !== []) {
            throw new LifecycleRollbackBlockedException($recordedData);
        }
    }

    /**
     * @return array<string, int> Description => row count, only for non-empty sources.
     */
    public function recordedLifecycleData(): array
    {
        $connection = DB::connection();
        $schema = $connection->getSchemaBuilder();
        $counts = [];

        foreach (self::HISTORY_TABLES as $table) {
            if ($schema->hasTable($table)) {
                $counts[$table] = $connection->table($table)->count();
            }
        }

        $counts['inventory_movements (unit events)'] = $connection->table('inventory_movements')
            ->where(fn ($query) => $query->whereIn('type', self::UNIT_EVENT_TYPES)->orWhere('quantity_change', 0))
            ->count();

        $counts['inventory_units (inspection or in repair)'] = $connection->table('inventory_units')
            ->whereIn('status', self::LIFECYCLE_STATUSES)
            ->count();

        if ($schema->hasColumn('inventory_units', 'lifecycle_revision')) {
            $counts['inventory_units (lifecycle revision above 0)'] = $connection->table('inventory_units')
                ->where('lifecycle_revision', '>', 0)
                ->count();
        }

        return array_filter($counts, fn (int $count): bool => $count > 0);
    }
}
