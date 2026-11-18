<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Enums\SaleLineCostStatus;
use App\Enums\TrackingMode;
use App\Models\DeviceCostEntry;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\User;
use App\Services\Inventory\DeviceSaleCost;
use App\Services\Purchasing\Money;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Owner “Sales margin based on recorded costs” figures from frozen sale-line snapshots.
 *
 * Summaries are aggregated in SQL. Detail rows are paginated separately. Restricted cost
 * amounts are omitted (not labeled unknown) when the viewer lacks the matching gate.
 */
final class SalesMarginReport
{
    public const PER_PAGE = 25;

    /**
     * @param  list<int>  $storeIds
     * @param  array<int, bool>  $canQuantity
     * @param  array<int, bool>  $canDevice
     * @return array<string, mixed>
     */
    public static function summarize(
        User $user,
        array $storeIds,
        CarbonImmutable $from,
        CarbonImmutable $to,
        array $canQuantity,
        array $canDevice,
    ): array {
        $empty = self::emptySummary(
            in_array(true, $canQuantity, true),
            in_array(true, $canDevice, true),
        );

        if ($storeIds === []) {
            return $empty;
        }

        [$start, $end] = LocalDateRange::utcBounds($from, $to);

        $salesQuery = self::salesInPeriod($storeIds, $start, $end);
        $recordedCount = (clone $salesQuery)->count();
        $recordedRevenue = self::decimalSumToMinor((clone $salesQuery)->sum('total'));

        if ($recordedCount === 0) {
            return $empty;
        }

        $networkSaleIds = self::networkSaleIds($storeIds, $start, $end);
        $networkSaleCount = count($networkSaleIds);
        $networkRevenue = $networkSaleIds === []
            ? 0
            : self::decimalSumToMinor(
                Sale::query()->whereKey($networkSaleIds)->sum('total')
            );

        $lineRows = DB::table('sale_lines')
            ->join('sales', 'sales.id', '=', 'sale_lines.sale_id')
            ->whereIn('sales.store_id', $storeIds)
            ->where('sales.completed_at', '>=', $start)
            ->where('sales.completed_at', '<', $end)
            ->when($networkSaleIds !== [], fn ($q) => $q->whereNotIn('sales.id', $networkSaleIds))
            ->groupBy('sales.store_id', 'sale_lines.cost_status', 'sale_lines.tracking_mode', 'sale_lines.cost_unknown_reason')
            ->select([
                'sales.store_id',
                'sale_lines.cost_status',
                'sale_lines.tracking_mode',
                'sale_lines.cost_unknown_reason',
                DB::raw('count(*) as line_count'),
                DB::raw('count(distinct sales.id) as sale_count'),
                DB::raw('sum(sale_lines.line_total) as revenue_total'),
                DB::raw('sum(case when sale_lines.cost_status = \'known\' then sale_lines.cost_amount else 0 end) as cost_total'),
            ])
            ->get();

        $knownLineCount = 0;
        $knownRevenue = 0;
        $visibleKnownRevenue = 0;
        $knownCost = 0;
        $unknownLineCount = 0;
        $unknownRevenue = 0;
        $unknownReasons = [];

        foreach ($lineRows as $row) {
            $storeId = (int) $row->store_id;
            $status = $row->cost_status;
            $isQuantity = (string) $row->tracking_mode === TrackingMode::QUANTITY->value;
            $canSee = $isQuantity ? ($canQuantity[$storeId] ?? false) : ($canDevice[$storeId] ?? false);
            $revenue = self::decimalSumToMinor($row->revenue_total);
            $lineCount = (int) $row->line_count;

            if ((string) $status === SaleLineCostStatus::KNOWN->value) {
                $knownLineCount += $lineCount;
                $knownRevenue = Money::sumUncapped([$knownRevenue, $revenue]);

                if ($canSee) {
                    $visibleKnownRevenue = Money::sumUncapped([$visibleKnownRevenue, $revenue]);
                    $knownCost = Money::sumUncapped([$knownCost, self::decimalSumToMinor($row->cost_total)]);
                }

                continue;
            }

            // null / unknown / anything else on non-network sales counts as unknown coverage.
            $unknownLineCount += $lineCount;
            $unknownRevenue = Money::sumUncapped([$unknownRevenue, $revenue]);
            $reason = $row->cost_unknown_reason ?: ($status === null || $status === '' ? 'pre_activation' : 'pool_unknown');
            $unknownReasons[$reason] = ($unknownReasons[$reason] ?? 0) + $lineCount;
        }

        $knownSaleCount = (int) DB::table('sale_lines')
            ->join('sales', 'sales.id', '=', 'sale_lines.sale_id')
            ->whereIn('sales.store_id', $storeIds)
            ->where('sales.completed_at', '>=', $start)
            ->where('sales.completed_at', '<', $end)
            ->when($networkSaleIds !== [], fn ($q) => $q->whereNotIn('sales.id', $networkSaleIds))
            ->where('sale_lines.cost_status', SaleLineCostStatus::KNOWN->value)
            ->selectRaw('count(distinct sales.id) as aggregate')
            ->value('aggregate');

        $coverageSum = Money::sumUncapped([$knownRevenue, $unknownRevenue, $networkRevenue]);

        return [
            'recorded_sales_count' => $recordedCount,
            'recorded_revenue_minor' => $recordedRevenue,
            'known_line_count' => $knownLineCount,
            'known_sale_count' => $knownSaleCount,
            'known_revenue_minor' => $knownRevenue,
            'visible_known_revenue_minor' => $visibleKnownRevenue,
            'restricted_known_revenue_minor' => $knownRevenue - $visibleKnownRevenue,
            'known_cost_minor' => $knownCost,
            'known_margin_minor' => $visibleKnownRevenue - $knownCost,
            'unknown_line_count' => $unknownLineCount,
            'unknown_revenue_minor' => $unknownRevenue,
            'unknown_reasons' => $unknownReasons,
            'network_sale_count' => $networkSaleCount,
            'network_revenue_minor' => $networkRevenue,
            'coverage' => [
                'known' => $knownRevenue,
                'unknown' => $unknownRevenue,
                'network' => $networkRevenue,
                'sum' => $coverageSum,
            ],
            'can_see_quantity_costs' => in_array(true, $canQuantity, true),
            'can_see_device_costs' => in_array(true, $canDevice, true),
            'chart_fingerprint' => substr(hash('xxh128', json_encode([
                $knownRevenue,
                $unknownRevenue,
                $networkRevenue,
                $unknownReasons,
                $knownLineCount,
                $unknownLineCount,
                $networkSaleCount,
            ], JSON_THROW_ON_ERROR)), 0, 16),
        ];
    }

    /**
     * @param  list<int>  $storeIds
     * @param  array<int, bool>  $canQuantity
     * @param  array<int, bool>  $canDevice
     */
    public static function paginateSales(
        User $user,
        array $storeIds,
        CarbonImmutable $from,
        CarbonImmutable $to,
        array $canQuantity,
        array $canDevice,
        int $page = 1,
    ): LengthAwarePaginator {
        if ($storeIds === []) {
            return Sale::query()->whereKey([])->paginate(self::PER_PAGE, page: $page);
        }

        [$start, $end] = LocalDateRange::utcBounds($from, $to);

        $paginator = self::salesInPeriod($storeIds, $start, $end)
            ->with([
                'store:id,code,name',
                'lines' => fn ($q) => $q->orderBy('line_number')->with([
                    'costFacts',
                    'costDeclaration:id,quantity,declared_total,reason,declared_at,resulting_cost_total',
                ]),
            ])
            ->paginate(self::PER_PAGE, page: $page);

        $deviceUnitIds = $paginator->getCollection()
            ->flatMap(fn (Sale $sale) => $sale->lines)
            ->filter(fn (SaleLine $line) => $line->inventory_unit_id !== null
                && $line->tracking_mode !== TrackingMode::QUANTITY)
            ->pluck('inventory_unit_id')
            ->unique()
            ->values()
            ->all();

        $entriesByUnit = $deviceUnitIds === []
            ? collect()
            : DeviceCostEntry::query()
                ->whereIn('inventory_unit_id', $deviceUnitIds)
                ->orderBy('id')
                ->get()
                ->groupBy('inventory_unit_id');

        $paginator->setCollection(
            $paginator->getCollection()->map(
                fn (Sale $sale): array => self::saleRow($sale, $canQuantity, $canDevice, $entriesByUnit, $user)
            )
        );

        return $paginator;
    }

    /**
     * @param  array<int, bool>  $canQuantity
     * @param  array<int, bool>  $canDevice
     * @param  Collection<int|string, Collection<int, DeviceCostEntry>>  $entriesByUnit
     * @return array<string, mixed>
     */
    private static function saleRow(
        Sale $sale,
        array $canQuantity,
        array $canDevice,
        Collection $entriesByUnit,
        User $user,
    ): array {
        $storeId = (int) $sale->store_id;
        $lines = $sale->lines;
        $saleRevenue = $sale->totalMinor();
        $networkSale = $lines->contains(
            fn (SaleLine $line): bool => $line->cost_status === SaleLineCostStatus::EXCLUDED_NETWORK
        );

        if ($networkSale) {
            return [
                'sale_id' => $sale->id,
                'number' => $sale->number,
                'store_id' => $storeId,
                'store_code' => $sale->store?->code,
                'completed_at' => $sale->completed_at,
                'revenue_minor' => $saleRevenue,
                'classification' => 'network',
                'line_summaries' => [],
                'margin_minor' => null,
                'sale_url' => self::saleUrl($user, $sale),
            ];
        }

        $lineSummaries = [];
        $saleVisibleKnownRevenue = 0;
        $saleVisibleKnownCost = 0;
        $allKnown = true;
        $hasVisibleKnown = false;
        $hasRestrictedKnown = false;

        foreach ($lines as $line) {
            $line->setRelation('sale', $sale);
            $lineRevenue = $line->lineTotalMinor();
            $status = $line->cost_status;
            $isQuantity = $line->tracking_mode === TrackingMode::QUANTITY;
            $canSeeCost = $isQuantity ? ($canQuantity[$storeId] ?? false) : ($canDevice[$storeId] ?? false);

            if ($status === SaleLineCostStatus::KNOWN) {
                if ($canSeeCost) {
                    $cost = $line->costAmountMinor() ?? 0;
                    $saleVisibleKnownRevenue += $lineRevenue;
                    $saleVisibleKnownCost += $cost;
                    $hasVisibleKnown = true;
                    $summary = [
                        'line_number' => $line->line_number,
                        'status' => 'known',
                        'revenue_minor' => $lineRevenue,
                        'cost_minor' => $cost,
                        'margin_minor' => $lineRevenue - $cost,
                        'reason' => null,
                        'product_name' => $line->product_name,
                        'product_model' => $line->product_model,
                        'unit_identifier' => $line->unit_identifier,
                        'provenance' => self::provenance($line, $isQuantity, $canSeeCost, $entriesByUnit),
                    ];
                } else {
                    $hasRestrictedKnown = true;
                    $summary = [
                        'line_number' => $line->line_number,
                        'status' => 'restricted',
                        'revenue_minor' => $lineRevenue,
                        'cost_minor' => null,
                        'margin_minor' => null,
                        'reason' => 'restricted',
                        'product_name' => $line->product_name,
                        'product_model' => $line->product_model,
                        'unit_identifier' => $line->unit_identifier,
                        'provenance' => null,
                    ];
                }

                $lineSummaries[] = $summary;

                continue;
            }

            $allKnown = false;
            $reason = $line->cost_unknown_reason ?? ($status === null ? 'pre_activation' : 'pool_unknown');
            $lineSummaries[] = [
                'line_number' => $line->line_number,
                'status' => 'unknown',
                'revenue_minor' => $lineRevenue,
                'cost_minor' => null,
                'margin_minor' => null,
                'reason' => $reason,
                'product_name' => $line->product_name,
                'product_model' => $line->product_model,
                'unit_identifier' => $line->unit_identifier,
                'provenance' => $canSeeCost
                    ? self::provenance($line, $isQuantity, $canSeeCost, $entriesByUnit)
                    : null,
            ];
        }

        return [
            'sale_id' => $sale->id,
            'number' => $sale->number,
            'store_id' => $storeId,
            'store_code' => $sale->store?->code,
            'completed_at' => $sale->completed_at,
            'revenue_minor' => $saleRevenue,
            'classification' => ($allKnown && ! $hasRestrictedKnown && $hasVisibleKnown) ? 'known' : 'mixed_or_unknown',
            'line_summaries' => $lineSummaries,
            'margin_minor' => ($allKnown && $hasVisibleKnown && ! $hasRestrictedKnown)
                ? ($saleVisibleKnownRevenue - $saleVisibleKnownCost)
                : null,
            'sale_url' => self::saleUrl($user, $sale),
        ];
    }

    /**
     * @param  Collection<int|string, Collection<int, DeviceCostEntry>>  $entriesByUnit
     * @return array<string, mixed>|null
     */
    private static function provenance(
        SaleLine $line,
        bool $isQuantity,
        bool $canSeeCost,
        Collection $entriesByUnit,
    ): ?array {
        if (! $canSeeCost) {
            return null;
        }

        if ($isQuantity) {
            $declaration = $line->costDeclaration;

            return [
                'type' => 'quantity',
                'pool_quantity_before' => $line->pool_quantity_before,
                'pool_cost_total_before' => $line->pool_cost_total_before,
                'removed_cost' => $line->cost_amount,
                'declaration' => $declaration === null ? null : [
                    'id' => $declaration->id,
                    'quantity' => $declaration->quantity,
                    'declared_total' => $declaration->declared_total,
                    'reason' => $declaration->reason,
                    'declared_at' => $declaration->declared_at,
                ],
                'inventory_url' => $line->sale?->store_id === null
                    ? null
                    : route('subdomain.inventory.product', [
                        'store' => $line->sale->store_id,
                        'productDefinition' => $line->product_definition_id,
                    ]),
            ];
        }

        $activity = DeviceSaleCost::laterActivity(
            $line,
            $line->costFacts,
            $entriesByUnit->get($line->inventory_unit_id) ?? collect(),
        );

        return [
            'type' => 'device',
            'snapshot' => $activity['snapshot'],
            'later' => $activity['later'],
            'unit_url' => $line->inventory_unit_id === null || $line->sale?->store_id === null
                ? null
                : route('subdomain.inventory.unit', [
                    'store' => $line->sale->store_id,
                    'unit' => $line->inventory_unit_id,
                ]),
        ];
    }

    private static function saleUrl(User $user, Sale $sale): ?string
    {
        $store = $sale->store;

        if ($store === null || ! Gate::forUser($user)->allows('viewAllSales', $store)) {
            return null;
        }

        return route('pos.sales', ['store' => $store, 'q' => $sale->number]);
    }

    /**
     * @param  list<int>  $storeIds
     * @return Builder<Sale>
     */
    private static function salesInPeriod(array $storeIds, $start, $end): Builder
    {
        return Sale::query()
            ->whereIn('store_id', $storeIds)
            ->where('completed_at', '>=', $start)
            ->where('completed_at', '<', $end)
            ->orderBy('id');
    }

    /**
     * @param  list<int>  $storeIds
     * @return list<int>
     */
    private static function networkSaleIds(array $storeIds, $start, $end): array
    {
        return SaleLine::query()
            ->join('sales', 'sales.id', '=', 'sale_lines.sale_id')
            ->whereIn('sales.store_id', $storeIds)
            ->where('sales.completed_at', '>=', $start)
            ->where('sales.completed_at', '<', $end)
            ->where('sale_lines.cost_status', SaleLineCostStatus::EXCLUDED_NETWORK->value)
            ->distinct()
            ->pluck('sales.id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    private static function decimalSumToMinor(mixed $sum): int
    {
        if ($sum === null || $sum === '') {
            return 0;
        }

        if (is_int($sum)) {
            return Money::fromDecimal(Money::toDecimal($sum));
        }

        return Money::fromDecimal(is_string($sum) ? $sum : sprintf('%.2F', $sum));
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptySummary(bool $canQuantity, bool $canDevice): array
    {
        return [
            'recorded_sales_count' => 0,
            'recorded_revenue_minor' => 0,
            'known_line_count' => 0,
            'known_sale_count' => 0,
            'known_revenue_minor' => 0,
            'visible_known_revenue_minor' => 0,
            'restricted_known_revenue_minor' => 0,
            'known_cost_minor' => 0,
            'known_margin_minor' => 0,
            'unknown_line_count' => 0,
            'unknown_revenue_minor' => 0,
            'unknown_reasons' => [],
            'network_sale_count' => 0,
            'network_revenue_minor' => 0,
            'coverage' => ['known' => 0, 'unknown' => 0, 'network' => 0, 'sum' => 0],
            'can_see_quantity_costs' => $canQuantity,
            'can_see_device_costs' => $canDevice,
            'chart_fingerprint' => 'empty',
        ];
    }
}
