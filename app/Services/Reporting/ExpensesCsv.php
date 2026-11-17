<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Models\Expense;
use App\Models\Store;
use App\Services\Purchasing\Money;
use Illuminate\Database\Eloquent\Builder;

/**
 * Authoritative filtered expense history query shared by the expenses screen and CSV download.
 */
final class ExpensesCsv
{
    /**
     * @return Builder<Expense>
     */
    public static function query(Store $store, string $from, string $to, string $category): Builder
    {
        return Expense::query()
            ->where('store_id', $store->id)
            ->when($from !== '', fn ($query) => $query->onBusinessDays($from, $to))
            ->when($category !== '', fn ($query) => $query->where('category', $category));
    }

    /**
     * @param  Builder<Expense>  $filtered
     * @return \Generator<int, list<string>>
     */
    public static function rows(Builder $filtered): \Generator
    {
        $query = $filtered
            ->clone()
            ->with(['recorder:id,name', 'supplier:id,name'])
            ->reorder()
            ->orderByDesc('expense_date')
            ->orderByDesc('id');

        foreach ($query->lazy(ReportCsvBatch::size()) as $expense) {
            yield [
                (string) $expense->expense_date->format('Y-m-d'),
                CsvExporter::text($expense->category->label()),
                CsvExporter::text($expense->description),
                CsvExporter::amount(Money::toDecimal($expense->amountMinor())),
                $expense->currency->value,
                CsvExporter::text($expense->method->label()),
                CsvExporter::text($expense->reference),
                CsvExporter::text($expense->recorder?->name),
                CsvExporter::text($expense->supplier?->name),
            ];
        }
    }

    /**
     * @return list<string>
     */
    public static function headers(): array
    {
        return [
            __('reports.export.expenses.date'),
            __('reports.export.expenses.category'),
            __('reports.export.expenses.description'),
            __('reports.export.expenses.amount'),
            __('reports.export.expenses.currency'),
            __('reports.export.expenses.method'),
            __('reports.export.expenses.reference'),
            __('reports.export.expenses.recorder'),
            __('reports.export.expenses.supplier'),
        ];
    }
}
