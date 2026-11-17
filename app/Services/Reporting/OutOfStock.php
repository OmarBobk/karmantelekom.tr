<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Enums\TrackingMode;
use App\Enums\UnitStatus;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\ProductDefinition;
use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Active products previously stocked at a branch that currently have no physical stock there.
 *
 * Previously stocked means an inventory movement exists for that branch and definition. Quantity
 * products use on-hand balance quantity; serialized products use AVAILABLE units at the branch.
 * Used devices are never included. Storefront holds and reservations do not change these figures.
 */
final class OutOfStock
{
    public const FILTER = 'out';

    /**
     * Active quantity and new-serialized definitions that are out of stock at the branch.
     *
     * @return Builder<ProductDefinition>
     */
    public static function definitions(Store $store): Builder
    {
        $storeId = $store->id;

        return ProductDefinition::query()
            ->active()
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $quantity) => self::quantityOutOfStock($quantity, $storeId))
                ->orWhere(fn (Builder $serialized) => self::serializedOutOfStock($serialized, $storeId)));
    }

    /**
     * Out-of-stock product counts for the given branches, keyed by store id.
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
    private static function quantityOutOfStock(Builder $query, int $storeId): void
    {
        $query->where('tracking_mode', TrackingMode::QUANTITY)
            ->whereExists(self::movementExists($storeId))
            ->where(fn (Builder $stock) => $stock
                ->whereNotExists(fn ($balance) => $balance->select(DB::raw(1))
                    ->from('inventory_balances')
                    ->whereColumn('inventory_balances.product_definition_id', 'product_definitions.id')
                    ->where('inventory_balances.store_id', $storeId)
                    ->where('inventory_balances.quantity', '>', 0)));
    }

    /**
     * @param  Builder<ProductDefinition>  $query
     */
    private static function serializedOutOfStock(Builder $query, int $storeId): void
    {
        $query->where('tracking_mode', TrackingMode::SERIALIZED)
            ->whereExists(self::movementExists($storeId))
            ->whereNotExists(fn ($units) => $units->select(DB::raw(1))
                ->from('inventory_units')
                ->whereColumn('inventory_units.product_definition_id', 'product_definitions.id')
                ->where('inventory_units.store_id', $storeId)
                ->where('inventory_units.status', UnitStatus::AVAILABLE->value));
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
            ->whereIn('inventory_balances.store_id', $storeIds)
            ->where('inventory_balances.quantity', 0)
            ->where('product_definitions.is_active', true)
            ->where('product_definitions.tracking_mode', TrackingMode::QUANTITY->value)
            ->whereExists(fn ($movements) => $movements->select(DB::raw(1))
                ->from('inventory_movements')
                ->whereColumn('inventory_movements.product_definition_id', 'inventory_balances.product_definition_id')
                ->whereColumn('inventory_movements.store_id', 'inventory_balances.store_id'))
            ->select('inventory_balances.store_id', 'inventory_balances.product_definition_id');

        $serialized = InventoryMovement::query()
            ->toBase()
            ->join('product_definitions', 'product_definitions.id', '=', 'inventory_movements.product_definition_id')
            ->whereIn('inventory_movements.store_id', $storeIds)
            ->where('product_definitions.is_active', true)
            ->where('product_definitions.tracking_mode', TrackingMode::SERIALIZED->value)
            ->whereNotExists(fn ($units) => $units->select(DB::raw(1))
                ->from('inventory_units')
                ->whereColumn('inventory_units.product_definition_id', 'inventory_movements.product_definition_id')
                ->whereColumn('inventory_units.store_id', 'inventory_movements.store_id')
                ->where('inventory_units.status', UnitStatus::AVAILABLE->value))
            ->select('inventory_movements.store_id', 'inventory_movements.product_definition_id')
            ->distinct();

        return DB::query()
            ->fromSub($quantity->union($serialized), 'out_of_stock')
            ->select('store_id', DB::raw('count(*) as product_count'))
            ->groupBy('store_id')
            ->get()
            ->all();
    }
}
