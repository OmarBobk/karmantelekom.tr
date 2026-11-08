<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Refuses to roll back a Phase 6.5 device reconciliation migration while its data exists.
 *
 * Each of those down() methods calls this first, so a batch rollback checks all of it.
 * It reads the schema directly because it runs inside migrations.
 */
class ReconciliationRollbackGuard
{
    public const REVIEW_STATUS = 'review';

    public const REVIEW_APPROVED_TYPE = 'review_approved';

    /**
     * @throws RuntimeException
     */
    public function assertNoRecordedReconciliationData(): void
    {
        $connection = DB::connection();
        $schema = $connection->getSchemaBuilder();

        $counts = [
            'inventory_units (awaiting review)' => $connection->table('inventory_units')->where('status', self::REVIEW_STATUS)->count(),
            'inventory_movements (review approvals)' => $connection->table('inventory_movements')->where('type', self::REVIEW_APPROVED_TYPE)->count(),
            'inventory_movements (review status history)' => $connection->table('inventory_movements')
                ->where(fn ($query) => $query->where('unit_status_before', self::REVIEW_STATUS)->orWhere('unit_status_after', self::REVIEW_STATUS))
                ->count(),
        ];

        if ($schema->hasColumn('stores', 'inventory_setup_completed_at')) {
            $counts['stores (inventory setup completed)'] = $connection->table('stores')->whereNotNull('inventory_setup_completed_at')->count();
        }

        $recorded = array_filter($counts, fn (int $count): bool => $count > 0);

        if ($recorded !== []) {
            $summary = collect($recorded)->map(fn (int $count, string $description): string => "{$description}: {$count}")->implode('; ');

            throw new RuntimeException("Phase 6.5 rollback refused: reconciliation data exists ({$summary}). Nothing was changed. Fix forward with a new migration instead of rolling back.");
        }
    }
}
