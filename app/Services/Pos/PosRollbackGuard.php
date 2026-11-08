<?php

declare(strict_types=1);

namespace App\Services\Pos;

use App\Exceptions\Pos\PosRollbackBlockedException;
use Illuminate\Support\Facades\DB;

/**
 * Refuses to roll back any Phase 5 migration while POS data exists.
 *
 * Every Phase 5 down() calls this first. It reads the schema directly instead of through
 * models because it runs inside migrations, and checks all Phase 5 data, not only the
 * migration's own table, because a batch rollback runs the newest migration first.
 */
class PosRollbackGuard
{
    public const TABLES = [
        'supplier_cash_payouts',
        'expense_submissions',
        'expenses',
        'cash_payouts',
        'cash_movements',
        'cash_intakes',
        'cashbox_sessions',
        'customer_payment_allocations',
        'customer_payments',
        'customer_payment_number_sequences',
        'customer_debts',
        'sale_payments',
        'sale_lines',
        'sales',
        'sale_number_sequences',
        'card_approvals',
        'checkout_attempts',
        'card_terminals',
        'pos_discount_grants',
    ];

    /**
     * @throws PosRollbackBlockedException
     */
    public function assertNoRecordedPosData(): void
    {
        $recordedData = $this->recordedPosData();

        if ($recordedData !== []) {
            throw new PosRollbackBlockedException($recordedData);
        }
    }

    /**
     * @return array<string, int> Description => row count, only for non-empty sources.
     */
    public function recordedPosData(): array
    {
        $connection = DB::connection();
        $schema = $connection->getSchemaBuilder();
        $counts = [];

        foreach (self::TABLES as $table) {
            if ($schema->hasTable($table)) {
                $counts[$table] = $connection->table($table)->count();
            }
        }

        $counts['inventory_movements (sales)'] = $connection->table('inventory_movements')->where('type', 'sale')->count();
        $counts['inventory_units (sold)'] = $connection->table('inventory_units')->where('status', 'sold')->count();

        foreach (['product_definitions', 'inventory_units'] as $table) {
            if ($schema->hasColumn($table, 'selling_price')) {
                $counts["{$table} (selling price set)"] = $connection->table($table)->whereNotNull('selling_price')->count();
            }
        }

        if ($schema->hasColumn('inventory_units', 'minimum_selling_price')) {
            $counts['inventory_units (minimum selling price set)'] = $connection->table('inventory_units')->whereNotNull('minimum_selling_price')->count();
        }

        return array_filter($counts, fn (int $count): bool => $count > 0);
    }
}
