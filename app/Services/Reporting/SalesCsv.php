<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Enums\SalePaymentMethod;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use App\Services\Purchasing\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Authoritative filtered sales query shared by the history screen and the CSV download.
 */
final class SalesCsv
{
    /**
     * Completed sales the user may see in this store that match search, Istanbul date bounds,
     * and an optional tender filter (cash, card, or original credit).
     *
     * @return Builder<Sale>
     */
    public static function query(User $user, Store $store, string $search, string $from, string $to, string $tender = ''): Builder
    {
        $term = Str::trim($search);
        $like = '%'.addcslashes($term, '%_\\').'%';
        $tender = Str::trim($tender);

        return Sale::query()
            ->visibleTo($user, $store)
            ->when($term !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('number', 'like', $like)
                ->orWhereHas('lines', fn ($lines) => $lines
                    ->where('unit_identifier', $term)
                    ->orWhereHas('unit.identifiers', fn ($identifiers) => $identifiers->where('value', mb_strtoupper($term)))
                    ->orWhere('product_name', 'like', $like)
                    ->orWhere('sku', $term)
                    ->orWhere('barcode', $term))
                ->orWhereHas('payments', fn ($payments) => $payments->where('terminal_reference', $term))))
            ->when($from !== '', fn ($query) => $query->completedOnLocalDays(LocalDateRange::date($from), LocalDateRange::date($to)))
            ->when(RecordedSalesReport::isTender($tender), fn ($query) => match ($tender) {
                RecordedSalesReport::TENDER_CASH => $query->whereHas('payments', fn ($payments) => $payments->where('method', SalePaymentMethod::CASH)),
                RecordedSalesReport::TENDER_CARD => $query->whereHas('payments', fn ($payments) => $payments->where('method', SalePaymentMethod::CARD)),
                RecordedSalesReport::TENDER_CREDIT => $query->whereHas('debt'),
                default => $query,
            });
    }

    /**
     * @param  Builder<Sale>  $filtered
     * @return \Generator<int, list<string>>
     */
    public static function rows(Builder $filtered): \Generator
    {
        $query = $filtered
            ->clone()
            ->with(['cashier:id,name', 'customer:id,name,phone_normalized', 'debt:id,sale_id,original_amount'])
            ->withCount('lines')
            ->reorder()
            ->orderByDesc('completed_at')
            ->orderByDesc('id');

        foreach ($query->lazy(ReportCsvBatch::size()) as $sale) {
            yield [
                CsvExporter::text($sale->number),
                Sale::displayTime($sale->completed_at),
                CsvExporter::text($sale->cashier?->name),
                CsvExporter::text($sale->customer?->name),
                CsvExporter::text($sale->customer?->phone_normalized),
                (string) $sale->lines_count,
                CsvExporter::amount(Money::toDecimal($sale->totalMinor())),
                CsvExporter::amount(Money::toDecimal($sale->paidMinor())),
                CsvExporter::amount(Money::toDecimal($sale->creditMinor())),
                $sale->currency->value,
            ];
        }
    }

    /**
     * @return list<string>
     */
    public static function headers(): array
    {
        return [
            __('reports.export.sales.number'),
            __('reports.export.sales.completed_at'),
            __('reports.export.sales.cashier'),
            __('reports.export.sales.customer'),
            __('reports.export.sales.customer_phone'),
            __('reports.export.sales.lines'),
            __('reports.export.sales.total'),
            __('reports.export.sales.paid'),
            __('reports.export.sales.credit'),
            __('reports.export.sales.currency'),
        ];
    }
}
