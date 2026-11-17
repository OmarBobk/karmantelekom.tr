<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Enums\SaleLineCostStatus;
use App\Enums\TrackingMode;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\Store;
use App\Models\User;
use App\Services\Inventory\DeviceSaleCost;
use App\Services\Purchasing\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

/**
 * Owner “Sales margin based on recorded costs” figures from frozen sale-line snapshots.
 *
 * Restricted cost amounts are omitted (not labeled unknown) when the viewer lacks the matching
 * purchase or device-cost gate for that branch and line type.
 */
final class SalesMarginReport
{
    /**
     * @param  list<int>  $storeIds
     * @return array{
     *     recorded_sales_count: int,
     *     recorded_revenue_minor: int,
     *     known_line_count: int,
     *     known_sale_count: int,
     *     known_revenue_minor: int,
     *     visible_known_revenue_minor: int,
     *     restricted_known_revenue_minor: int,
     *     known_cost_minor: int,
     *     known_margin_minor: int,
     *     unknown_line_count: int,
     *     unknown_revenue_minor: int,
     *     unknown_reasons: array<string, int>,
     *     network_sale_count: int,
     *     network_revenue_minor: int,
     *     coverage: array{known: int, unknown: int, network: int, sum: int},
     *     rows: list<array<string, mixed>>,
     *     can_see_quantity_costs: bool,
     *     can_see_device_costs: bool,
     * }
     */
    public static function forStores(User $user, array $storeIds, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $empty = self::emptyResult(false, false);

        if ($storeIds === []) {
            return $empty;
        }

        [$start, $end] = LocalDateRange::utcBounds($from, $to);

        $sales = Sale::query()
            ->whereIn('store_id', $storeIds)
            ->where('completed_at', '>=', $start)
            ->where('completed_at', '<', $end)
            ->with([
                'lines' => fn ($q) => $q->orderBy('line_number')->with('costFacts'),
                'store:id,code,name',
            ])
            ->orderBy('id')
            ->get();

        $storesById = Store::query()->whereKey($storeIds)->get()->keyBy('id');
        $canQuantity = [];
        $canDevice = [];

        foreach ($storeIds as $storeId) {
            $store = $storesById->get($storeId);
            $canQuantity[$storeId] = $store !== null && Gate::forUser($user)->allows('managePurchases', $store);
            $canDevice[$storeId] = $store !== null && Gate::forUser($user)->allows('manageDeviceCosts', $store);
        }

        $recordedCount = $sales->count();
        $recordedRevenue = 0;
        $knownLineCount = 0;
        $knownSaleIds = [];
        $knownRevenue = 0;
        $visibleKnownRevenue = 0;
        $knownCost = 0;
        $unknownLineCount = 0;
        $unknownRevenue = 0;
        $unknownReasons = [];
        $networkSaleIds = [];
        $networkRevenue = 0;
        $rows = [];
        $anyQuantity = in_array(true, $canQuantity, true);
        $anyDevice = in_array(true, $canDevice, true);

        foreach ($sales as $sale) {
            $saleRevenue = $sale->totalMinor();
            $recordedRevenue = Money::sumUncapped([$recordedRevenue, $saleRevenue]);
            $storeId = (int) $sale->store_id;
            $lines = $sale->lines;
            $networkSale = $lines->contains(
                fn (SaleLine $line): bool => $line->cost_status === SaleLineCostStatus::EXCLUDED_NETWORK
            );

            if ($networkSale) {
                $networkSaleIds[$sale->id] = true;
                $networkRevenue = Money::sumUncapped([$networkRevenue, $saleRevenue]);
                $rows[] = [
                    'sale_id' => $sale->id,
                    'number' => $sale->number,
                    'store_code' => $sale->store?->code,
                    'completed_at' => $sale->completed_at,
                    'revenue_minor' => $saleRevenue,
                    'classification' => 'network',
                    'line_summaries' => [],
                    'margin_minor' => null,
                    'device_drilldowns' => [],
                ];

                continue;
            }

            $lineSummaries = [];
            $deviceDrilldowns = [];
            $saleVisibleKnownRevenue = 0;
            $saleVisibleKnownCost = 0;
            $saleUnknownRevenue = 0;
            $allKnown = true;
            $hasVisibleKnown = false;
            $hasRestrictedKnown = false;

            foreach ($lines as $line) {
                $lineRevenue = $line->lineTotalMinor();
                $status = $line->cost_status;
                $isQuantity = $line->tracking_mode === TrackingMode::QUANTITY;
                $canSeeCost = $isQuantity ? ($canQuantity[$storeId] ?? false) : ($canDevice[$storeId] ?? false);

                if ($status === SaleLineCostStatus::KNOWN) {
                    $knownLineCount++;
                    $knownSaleIds[$sale->id] = true;
                    $knownRevenue = Money::sumUncapped([$knownRevenue, $lineRevenue]);

                    if ($canSeeCost) {
                        $cost = $line->costAmountMinor() ?? 0;
                        $knownCost = Money::sumUncapped([$knownCost, $cost]);
                        $visibleKnownRevenue = Money::sumUncapped([$visibleKnownRevenue, $lineRevenue]);
                        $saleVisibleKnownRevenue += $lineRevenue;
                        $saleVisibleKnownCost += $cost;
                        $hasVisibleKnown = true;
                        $lineSummaries[] = [
                            'line_number' => $line->line_number,
                            'status' => 'known',
                            'revenue_minor' => $lineRevenue,
                            'cost_minor' => $cost,
                            'margin_minor' => $lineRevenue - $cost,
                            'reason' => null,
                        ];

                        if (! $isQuantity && $canDevice[$storeId]) {
                            $line->setRelation('sale', $sale);
                            $deviceDrilldowns[$line->line_number] = DeviceSaleCost::laterActivity($line);
                        }
                    } else {
                        $hasRestrictedKnown = true;
                        $lineSummaries[] = [
                            'line_number' => $line->line_number,
                            'status' => 'restricted',
                            'revenue_minor' => $lineRevenue,
                            'cost_minor' => null,
                            'margin_minor' => null,
                            'reason' => 'restricted',
                        ];
                    }

                    continue;
                }

                $allKnown = false;
                $unknownLineCount++;
                $unknownRevenue = Money::sumUncapped([$unknownRevenue, $lineRevenue]);
                $saleUnknownRevenue += $lineRevenue;
                $reason = $line->cost_unknown_reason ?? ($status === null ? 'pre_activation' : 'pool_unknown');
                $unknownReasons[$reason] = ($unknownReasons[$reason] ?? 0) + 1;
                $lineSummaries[] = [
                    'line_number' => $line->line_number,
                    'status' => 'unknown',
                    'revenue_minor' => $lineRevenue,
                    'cost_minor' => null,
                    'margin_minor' => null,
                    'reason' => $reason,
                ];

                if (! $isQuantity && $canDevice[$storeId] && $status === SaleLineCostStatus::UNKNOWN) {
                    $line->setRelation('sale', $sale);
                    $deviceDrilldowns[$line->line_number] = DeviceSaleCost::laterActivity($line);
                }
            }

            $classification = $allKnown && ! $hasRestrictedKnown && $hasVisibleKnown
                ? 'known'
                : 'mixed_or_unknown';

            $rows[] = [
                'sale_id' => $sale->id,
                'number' => $sale->number,
                'store_code' => $sale->store?->code,
                'completed_at' => $sale->completed_at,
                'revenue_minor' => $saleRevenue,
                'classification' => $classification,
                'line_summaries' => $lineSummaries,
                'margin_minor' => ($allKnown && $hasVisibleKnown && ! $hasRestrictedKnown)
                    ? ($saleVisibleKnownRevenue - $saleVisibleKnownCost)
                    : null,
                'device_drilldowns' => $deviceDrilldowns,
            ];
        }

        $coverageKnown = $knownRevenue;
        $coverageUnknown = $unknownRevenue;
        $coverageNetwork = $networkRevenue;
        $coverageSum = Money::sumUncapped([$coverageKnown, $coverageUnknown, $coverageNetwork]);

        return [
            'recorded_sales_count' => $recordedCount,
            'recorded_revenue_minor' => $recordedRevenue,
            'known_line_count' => $knownLineCount,
            'known_sale_count' => count($knownSaleIds),
            'known_revenue_minor' => $knownRevenue,
            'visible_known_revenue_minor' => $visibleKnownRevenue,
            'restricted_known_revenue_minor' => $knownRevenue - $visibleKnownRevenue,
            'known_cost_minor' => $knownCost,
            'known_margin_minor' => $visibleKnownRevenue - $knownCost,
            'unknown_line_count' => $unknownLineCount,
            'unknown_revenue_minor' => $unknownRevenue,
            'unknown_reasons' => $unknownReasons,
            'network_sale_count' => count($networkSaleIds),
            'network_revenue_minor' => $networkRevenue,
            'coverage' => [
                'known' => $coverageKnown,
                'unknown' => $coverageUnknown,
                'network' => $coverageNetwork,
                'sum' => $coverageSum,
            ],
            'rows' => $rows,
            'can_see_quantity_costs' => $anyQuantity,
            'can_see_device_costs' => $anyDevice,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyResult(bool $canQuantity, bool $canDevice): array
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
            'rows' => [],
            'can_see_quantity_costs' => $canQuantity,
            'can_see_device_costs' => $canDevice,
        ];
    }
}
