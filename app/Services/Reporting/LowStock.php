<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Enums\TrackingMode;
use App\Enums\UnitStatus;
use App\Models\InventoryBalance;
use App\Models\InventoryStockThreshold;
use App\Models\ProductDefinition;
use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Active products previously stocked at a branch whose physical stock is at or below
 * an explicit per-branch threshold, but still above zero.
 *
 * Quantity products use on-hand balance quantity; new serialized products use AVAILABLE
 * units at the branch. Used devices are never included. Zero stock stays under OutOfStock
 * only. Storefront holds and reservations do not change these figures.
 */
final class LowStock
{
    public const FILTER = 'low';

    /**
     * Active quantity and new-serialized definitions that are low at the branch.
     *
     * @return Builder<ProductDefinition>
     */
    public static function definitions(Store $store): Builder
    {
        $storeId = $store->id;

        return ProductDefinition::query()
            ->active()
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $quantity) => self::quantityLow($quantity, $storeId))
                ->orWhere(fn (Builder $serialized) => self::serializedLow($serialized, $storeId)));
    }

    /**
     * Low-stock product counts for the given branches, keyed by store id.
     *
     * @param  list<int>  $storeIds
     * @return array<int, int>
     */
    public static function countsByStore(array $storeIds): array
    {
        $counts = array_fill_keys($storeIds, 0);

        if ($storeIds === []) {
            return $counts;
        }

        foreach (self::countRows($storeIds) as $row) {
            $counts[(int) $row->store_id] = (int) $row->product_count;
        }

        return $counts;
    }

    /**
     * @param  Builder<ProductDefinition>  $query
     */
    private static function quantityLow(Builder $query, int $storeId): void
    {
        $query->where('tracking_mode', TrackingMode::QUANTITY)
            ->whereExists(self::thresholdExists($storeId))
            ->whereExists(self::movementExists($storeId))
            ->whereExists(fn ($balance) => $balance->select(DB::raw(1))
                ->from('inventory_balances')
                ->join('inventory_stock_thresholds', function ($join) use ($storeId): void {
                    $join->on('inventory_stock_thresholds.product_definition_id', '=', 'inventory_balances.product_definition_id')
                        ->where('inventory_stock_thresholds.store_id', $storeId);
                })
                ->whereColumn('inventory_balances.product_definition_id', 'product_definitions.id')
                ->where('inventory_balances.store_id', $storeId)
                ->where('inventory_balances.quantity', '>', 0)
                ->whereColumn('inventory_balances.quantity', '<=', 'inventory_stock_thresholds.threshold'));
    }

    /**
     * @param  Builder<ProductDefinition>  $query
     */
    private static function serializedLow(Builder $query, int $storeId): void
    {
        $query->where('tracking_mode', TrackingMode::SERIALIZED)
            ->whereExists(self::thresholdExists($storeId))
            ->whereExists(self::movementExists($storeId))
            ->whereRaw(
                '(select count(*) from inventory_units
                    where inventory_units.product_definition_id = product_definitions.id
                      and inventory_units.store_id = ?
                      and inventory_units.status = ?) between 1 and (
                    select inventory_stock_thresholds.threshold
                    from inventory_stock_thresholds
                    where inventory_stock_thresholds.product_definition_id = product_definitions.id
                      and inventory_stock_thresholds.store_id = ?
                 )',
                [$storeId, UnitStatus::AVAILABLE->value, $storeId],
            );
    }

    /**
     * @return \Closure(\Illuminate\Database\Query\Builder): void
     */
    private static function thresholdExists(int $storeId): \Closure
    {
        return fn ($thresholds) => $thresholds->select(DB::raw(1))
            ->from('inventory_stock_thresholds')
            ->whereColumn('inventory_stock_thresholds.product_definition_id', 'product_definitions.id')
            ->where('inventory_stock_thresholds.store_id', $storeId)
            ->where('inventory_stock_thresholds.threshold', '>', 0);
    }

    /**
     * @return \Closure(\Illuminate\Database\Query\Builder): void
     */
    private static function movementExists(int $storeId): \Closure
    {
        return fn ($movements) => $movements->select(DB::raw(1))
            ->from('inventory_movements')
            ->whereColumn('inventory_movements.product_definition_id', 'product_definitions.id')
            ->where('inventory_movements.store_id', $storeId);
    }

    /**
     * @param  list<int>  $storeIds
     * @return list<object{store_id: int|string, product_count: int|string}>
     */
    private static function countRows(array $storeIds): array
    {
        $quantity = InventoryBalance::query()
            ->toBase()
            ->join('product_definitions', 'product_definitions.id', '=', 'inventory_balances.product_definition_id')
            ->join('inventory_stock_thresholds', function ($join): void {
                $join->on('inventory_stock_thresholds.store_id', '=', 'inventory_balances.store_id')
                    ->on('inventory_stock_thresholds.product_definition_id', '=', 'inventory_balances.product_definition_id');
            })
            ->whereIn('inventory_balances.store_id', $storeIds)
            ->where('inventory_balances.quantity', '>', 0)
            ->whereColumn('inventory_balances.quantity', '<=', 'inventory_stock_thresholds.threshold')
            ->where('product_definitions.is_active', true)
            ->where('product_definitions.tracking_mode', TrackingMode::QUANTITY->value)
            ->whereExists(fn ($movements) => $movements->select(DB::raw(1))
                ->from('inventory_movements')
                ->whereColumn('inventory_movements.product_definition_id', 'inventory_balances.product_definition_id')
                ->whereColumn('inventory_movements.store_id', 'inventory_balances.store_id'))
            ->select('inventory_balances.store_id', 'inventory_balances.product_definition_id');

        $serialized = InventoryStockThreshold::query()
            ->toBase()
            ->join('product_definitions', 'product_definitions.id', '=', 'inventory_stock_thresholds.product_definition_id')
            ->whereIn('inventory_stock_thresholds.store_id', $storeIds)
            ->where('product_definitions.is_active', true)
            ->where('product_definitions.tracking_mode', TrackingMode::SERIALIZED->value)
            ->whereExists(fn ($movements) => $movements->select(DB::raw(1))
                ->from('inventory_movements')
                ->whereColumn('inventory_movements.product_definition_id', 'inventory_stock_thresholds.product_definition_id')
                ->whereColumn('inventory_movements.store_id', 'inventory_stock_thresholds.store_id'))
            ->whereRaw(
                '(select count(*) from inventory_units
                    where inventory_units.product_definition_id = inventory_stock_thresholds.product_definition_id
                      and inventory_units.store_id = inventory_stock_thresholds.store_id
                      and inventory_units.status = ?) between 1 and inventory_stock_thresholds.threshold',
                [UnitStatus::AVAILABLE->value],
            )
            ->select('inventory_stock_thresholds.store_id', 'inventory_stock_thresholds.product_definition_id');

        return DB::query()
            ->fromSub($quantity->union($serialized), 'low_stock')
            ->select('store_id', DB::raw('count(*) as product_count'))
            ->groupBy('store_id')
            ->get()
            ->all();
    }
}
