<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Enums\SalePaymentMethod;
use App\Models\CustomerDebt;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Services\Purchasing\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Owner dashboard figures for completed POS sales: totals, previous equal period, tender
 * composition, and chart buckets. Cash payment amounts are the posted net cash due, not
 * cash tendered. Later debt collections are not sales and are never included.
 */
final class RecordedSalesReport
{
    public const TENDER_CASH = 'cash';

    public const TENDER_CARD = 'card';

    public const TENDER_CREDIT = 'credit';

    /** @var list<string> */
    public const TENDERS = [self::TENDER_CASH, self::TENDER_CARD, self::TENDER_CREDIT];

    /**
     * Count and recorded total per store for the Istanbul days.
     *
     * @param  list<int>  $storeIds
     * @return array<int, array{count: int, total_minor: int}>
     */
    public static function totalsByStore(array $storeIds, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return Sale::recordedTotalsByStore($storeIds, $from, $to);
    }

    /**
     * Cash, card, and original-credit parts of the same completed sales, per store.
     * Cash + card + original credit equals the recorded sale total for each store.
     *
     * @param  list<int>  $storeIds
     * @return array<int, array{cash: int, card: int, credit: int, total: int, count: int}>
     */
    public static function compositionByStore(array $storeIds, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if ($storeIds === []) {
            return [];
        }

        [$start, $end] = LocalDateRange::utcBounds($from, $to);
        $empty = array_fill_keys($storeIds, ['cash' => 0, 'card' => 0, 'credit' => 0, 'total' => 0, 'count' => 0]);

        $totals = Sale::recordedTotalsByStore($storeIds, $from, $to);

        foreach ($totals as $storeId => $row) {
            $empty[$storeId]['total'] = $row['total_minor'];
            $empty[$storeId]['count'] = $row['count'];
        }

        $payments = SalePayment::query()
            ->whereIn('store_id', $storeIds)
            ->whereHas('sale', fn ($sale) => $sale->whereIn('store_id', $storeIds)->where('completed_at', '>=', $start)->where('completed_at', '<', $end))
            ->toBase()
            ->selectRaw('store_id, method, coalesce(sum(amount), 0) as paid')
            ->groupBy('store_id', 'method')
            ->get();

        foreach ($payments as $row) {
            $storeId = (int) $row->store_id;
            $amount = Money::fromDecimal((string) $row->paid);

            if ($row->method === SalePaymentMethod::CASH->value) {
                $empty[$storeId]['cash'] = $amount;
            } elseif ($row->method === SalePaymentMethod::CARD->value) {
                $empty[$storeId]['card'] = $amount;
            }
        }

        $credits = CustomerDebt::query()
            ->whereIn('store_id', $storeIds)
            ->whereHas('sale', fn ($sale) => $sale->whereIn('store_id', $storeIds)->where('completed_at', '>=', $start)->where('completed_at', '<', $end))
            ->toBase()
            ->selectRaw('store_id, coalesce(sum(original_amount), 0) as credit')
            ->groupBy('store_id')
            ->get();

        foreach ($credits as $row) {
            $empty[(int) $row->store_id]['credit'] = Money::fromDecimal((string) $row->credit);
        }

        return $empty;
    }

    /**
     * Owner or branch rollup of composition rows.
     *
     * @param  array<int, array{cash: int, card: int, credit: int, total: int, count: int}>  $byStore
     * @return array{cash: int, card: int, credit: int, total: int, count: int}
     */
    public static function sumComposition(array $byStore): array
    {
        return [
            'cash' => Money::sumUncapped(array_column($byStore, 'cash')),
            'card' => Money::sumUncapped(array_column($byStore, 'card')),
            'credit' => Money::sumUncapped(array_column($byStore, 'credit')),
            'total' => Money::sumUncapped(array_column($byStore, 'total')),
            'count' => array_sum(array_column($byStore, 'count')),
        ];
    }

    /**
     * Time buckets for the selected range, filled with zero when a bucket has no sales.
     *
     * @param  list<int>  $storeIds
     * @return array{granularity: string, buckets: list<array{key: string, label: string, count: int, total_minor: int, start: string, end_date: string}>}
     */
    public static function series(array $storeIds, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $granularity = LocalDateRange::chartGranularity($from, $to);
        $buckets = LocalDateRange::buckets($from, $to, $granularity);
        $filled = [];

        foreach ($buckets as $bucket) {
            $filled[$bucket['key']] = [
                'key' => $bucket['key'],
                'label' => $bucket['label'],
                'count' => 0,
                'total_minor' => 0,
                'start' => $bucket['start']->setTimezone(Sale::DISPLAY_TIMEZONE)->toDateString(),
                'end_date' => $bucket['end']->setTimezone(Sale::DISPLAY_TIMEZONE)->subSecond()->toDateString(),
            ];
        }

        if ($storeIds === [] || $buckets === []) {
            return ['granularity' => $granularity, 'buckets' => array_values($filled)];
        }

        [$start, $end] = LocalDateRange::utcBounds($from, $to);
        $sales = Sale::query()
            ->whereIn('store_id', $storeIds)
            ->where('completed_at', '>=', $start)
            ->where('completed_at', '<', $end)
            ->toBase()
            ->select('completed_at', 'total')
            ->get();

        foreach ($sales as $sale) {
            $completed = CarbonImmutable::parse($sale->completed_at)->utc();
            $key = self::bucketKey($completed, $granularity, $from);

            if (! isset($filled[$key])) {
                continue;
            }

            $filled[$key]['count']++;
            $filled[$key]['total_minor'] = Money::sumUncapped([$filled[$key]['total_minor'], Money::fromDecimal((string) $sale->total)]);
        }

        return ['granularity' => $granularity, 'buckets' => array_values($filled)];
    }

    /**
     * Matching tender amount for the filtered sales query (cash/card payment sum or original credit).
     *
     * @param  Builder<Sale>  $filtered
     * @return array{count: int, total_minor: int, match_minor: int, tender: string}
     */
    public static function tenderSummary(Builder $filtered, string $tender): array
    {
        $summary = Sale::recordedSummary($filtered->clone());
        $saleIds = $filtered->clone()->toBase()->select('sales.id');

        $match = match ($tender) {
            self::TENDER_CASH, self::TENDER_CARD => Money::fromDecimal((string) (SalePayment::query()
                ->whereIn('sale_id', $saleIds)
                ->where('method', $tender === self::TENDER_CASH ? SalePaymentMethod::CASH : SalePaymentMethod::CARD)
                ->toBase()
                ->selectRaw('coalesce(sum(amount), 0) as paid')
                ->value('paid') ?? '0')),
            self::TENDER_CREDIT => Money::fromDecimal((string) (CustomerDebt::query()
                ->whereIn('sale_id', $saleIds)
                ->toBase()
                ->selectRaw('coalesce(sum(original_amount), 0) as credit')
                ->value('credit') ?? '0')),
            default => 0,
        };

        return [
            'count' => $summary['count'],
            'total_minor' => $summary['total_minor'],
            'match_minor' => $match,
            'tender' => $tender,
        ];
    }

    public static function isTender(string $value): bool
    {
        return in_array($value, self::TENDERS, true);
    }

    private static function bucketKey(CarbonImmutable $completedUtc, string $granularity, CarbonImmutable $rangeFrom): string
    {
        $local = $completedUtc->setTimezone(Sale::DISPLAY_TIMEZONE);

        return match ($granularity) {
            LocalDateRange::GRANULARITY_HOUR => $local->format('Y-m-d H:00'),
            LocalDateRange::GRANULARITY_DAY => $local->toDateString(),
            LocalDateRange::GRANULARITY_WEEK => self::weekBucketStart($local, $rangeFrom)->toDateString(),
            LocalDateRange::GRANULARITY_MONTH => $local->format('Y-m'),
            default => $local->toDateString(),
        };
    }

    private static function weekBucketStart(CarbonImmutable $local, CarbonImmutable $rangeFrom): CarbonImmutable
    {
        $from = $rangeFrom->setTimezone(Sale::DISPLAY_TIMEZONE)->startOfDay();
        $day = $local->startOfDay();
        $offset = $from->diffInDays($day);

        return $from->addDays(intdiv($offset, 7) * 7);
    }
}
