<?php

declare(strict_types=1);

namespace App\Actions\Storefront;

use App\Actions\Network\ListingEligibility;
use App\Enums\TrackingMode;
use App\Enums\UnitStatus;
use App\Models\NetworkReservation;
use App\Models\StorefrontAllocation;
use App\Models\StorefrontListing;
use App\Models\StorefrontOrderLine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;

/**
 * How many units of each linked storefront product customers can see as available, derived on
 * every read from the branches' online allocations and stock that could be sold at the counter
 * now. Retail and wholesale read the same number, so both channels draw from one allocation.
 *
 * A quantity allocation counts up to the branch's on-hand balance, less what confirmed storefront
 * orders hold there. A device counts while it is still in the allocating branch, available,
 * priced, on an active product, and not held by a Store Network reservation, a checkout taking
 * payment for one, or a confirmed storefront order. Sales, transfers, status changes,
 * reservations, and order holds therefore change the result without touching the allocation.
 *
 * Only counts leave this class: no branch, device, cost, or record identifiers.
 */
final class StorefrontAvailability
{
    /**
     * @param  iterable<int>  $productIds
     * @return array<int, int> Available units by storefront product id, for linked products only.
     */
    public function forProducts(iterable $productIds): array
    {
        $ids = collect($productIds)->map(fn (int|string $id): int => (int) $id)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $definitions = StorefrontListing::query()->whereIn('product_id', $ids)->pluck('product_definition_id', 'product_id');

        if ($definitions->isEmpty()) {
            return [];
        }

        $available = $this->forDefinitions($definitions->values()->map(fn (int|string $id): int => (int) $id)->all());

        return $definitions->mapWithKeys(fn (int|string $definitionId, int|string $productId): array => [(int) $productId => $available[(int) $definitionId] ?? 0])->all();
    }

    /**
     * @param  list<int>  $definitionIds
     * @return array<int, int> Available units by product definition id; missing ids have none.
     */
    public function forDefinitions(array $definitionIds): array
    {
        if ($definitionIds === []) {
            return [];
        }

        $held = StorefrontOrderLine::query()
            ->whereIn('product_definition_id', $definitionIds)
            ->where('held_quantity', '>', 0)
            ->tap(fn (Builder $query) => StorefrontOrderLine::whereHolding($query))
            ->groupBy('store_id', 'product_definition_id')
            ->selectRaw('store_id, product_definition_id, sum(held_quantity) as held');

        $quantities = StorefrontAllocation::query()
            ->join('product_definitions as definitions', 'definitions.id', '=', 'storefront_allocations.product_definition_id')
            ->join('inventory_balances as balances', fn (JoinClause $balance) => $balance
                ->on('balances.store_id', '=', 'storefront_allocations.store_id')
                ->on('balances.product_definition_id', '=', 'storefront_allocations.product_definition_id'))
            ->leftJoinSub($held, 'holds', fn (JoinClause $holds) => $holds
                ->on('holds.store_id', '=', 'storefront_allocations.store_id')
                ->on('holds.product_definition_id', '=', 'storefront_allocations.product_definition_id'))
            ->whereIn('storefront_allocations.product_definition_id', $definitionIds)
            ->whereNull('storefront_allocations.inventory_unit_id')
            ->where('storefront_allocations.quantity', '>', 0)
            ->where('balances.quantity', '>', 0)
            ->where('definitions.is_active', true)
            ->where('definitions.tracking_mode', TrackingMode::QUANTITY->value)
            ->whereNotNull('definitions.selling_price')
            ->groupBy('storefront_allocations.product_definition_id')
            ->selectRaw('storefront_allocations.product_definition_id as definition_id, sum(greatest(0, least(storefront_allocations.quantity, balances.quantity) - coalesce(holds.held, 0))) as available')
            ->pluck('available', 'definition_id');

        $devices = StorefrontAllocation::query()
            ->join('inventory_units as units', 'units.id', '=', 'storefront_allocations.inventory_unit_id')
            ->join('product_definitions as definitions', 'definitions.id', '=', 'units.product_definition_id')
            ->whereIn('storefront_allocations.product_definition_id', $definitionIds)
            ->where('storefront_allocations.quantity', '>', 0)
            ->whereColumn('units.store_id', 'storefront_allocations.store_id')
            ->whereColumn('units.product_definition_id', 'storefront_allocations.product_definition_id')
            ->where('units.status', UnitStatus::AVAILABLE->value)
            ->where('definitions.is_active', true)
            ->tap(fn (Builder $query) => ListingEligibility::wherePriced($query))
            ->tap(fn (Builder $query) => NetworkReservation::whereUnitNotHeld($query, 'units.id'))
            ->groupBy('storefront_allocations.product_definition_id')
            ->selectRaw('storefront_allocations.product_definition_id as definition_id, count(*) as available')
            ->pluck('available', 'definition_id');

        $available = [];

        foreach ([$quantities, $devices] as $counts) {
            foreach ($counts as $definitionId => $count) {
                $available[(int) $definitionId] = ($available[(int) $definitionId] ?? 0) + (int) $count;
            }
        }

        return $available;
    }
}
