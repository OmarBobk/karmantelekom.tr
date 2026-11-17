<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Services\Purchasing\Money;
use App\Services\Purchasing\SupplierAccount;

/**
 * Supplier ledger balances for CSV: one supplier/currency row, same query as the finance list.
 */
final class SupplierBalancesCsv
{
    /**
     * @param  list<int>  $storeIds
     * @return \Generator<int, list<string>>
     */
    public static function rows(SupplierAccount $accounts, array $storeIds): \Generator
    {
        foreach ($accounts->supplierBalanceRows($storeIds, ReportCsvBatch::size()) as $row) {
            yield [
                CsvExporter::text($row->name),
                $row->currency,
                CsvExporter::amount(Money::toDecimal(Money::fromDecimal((string) $row->balance))),
                CsvExporter::amount(Money::toDecimal(Money::fromDecimal((string) $row->open_invoices))),
            ];
        }
    }

    /**
     * @return list<string>
     */
    public static function headers(): array
    {
        return [
            __('reports.export.suppliers.supplier'),
            __('reports.export.suppliers.currency'),
            __('reports.export.suppliers.balance'),
            __('reports.export.suppliers.open_invoices'),
        ];
    }
}
