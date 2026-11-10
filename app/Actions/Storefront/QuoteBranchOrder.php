<?php

declare(strict_types=1);

namespace App\Actions\Storefront;

use App\Actions\Network\ListingEligibility;
use App\Actions\Pos\SellingPrice;
use App\Enums\TrackingMode;
use App\Enums\UnitStatus;
use App\Models\InventoryBalance;
use App\Models\InventoryUnit;
use App\Models\NetworkReservation;
use App\Models\ProductDefinition;
use App\Models\Store;
use App\Models\StorefrontAllocation;
use App\Models\StorefrontListing;
use App\Services\Purchasing\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Which kind of order a cart makes and, for linked branch stock, the branches that can fulfill
 * the whole cart now with their server prices in TRY. Read-only and unlocked: submission
 * repeats it, and nothing is held until a branch confirms.
 *
 * Quantity stock counts the branch's online allocation, up to its balance, less what confirmed
 * orders hold. Devices count while allocated, in the branch, available, priced, and not held by
 * a reservation or order. A used device line is priced at the branch's cheapest eligible devices;
 * the branch picks the exact device at confirmation, and a different price needs the buyer's
 * acceptance.
 */
final class QuoteBranchOrder
{
    public const LEGACY = 'legacy';

    public const BRANCH = 'branch';

    public const MIXED = 'mixed';

    /**
     * @param  Collection<int, int>  $quantities  Cart quantity by storefront product id.
     */
    public static function kind(Collection $quantities): string
    {
        $linked = StorefrontListing::query()->whereIn('product_id', $quantities->keys())->count();

        return match (true) {
            $linked === 0 => self::LEGACY,
            $linked === $quantities->count() => self::BRANCH,
            default => self::MIXED,
        };
    }

    /**
     * Branches that can fulfill every line, cheapest first.
     *
     * @param  Collection<int, int>  $quantities  Cart quantity by storefront product id; all linked.
     * @return list<array{store_id: int, name: string, code: string, total_minor: int, lines: list<array{product_id: int, definition_id: int, tracking_mode: TrackingMode, quantity: int, unit_prices: list<int>}>}>
     */
    public function branches(Collection $quantities): array
    {
        $definitionByProduct = StorefrontListing::query()->whereIn('product_id', $quantities->keys())->pluck('product_definition_id', 'product_id')
            ->map(fn (int|string $id): int => (int) $id);

        if ($definitionByProduct->count() !== $quantities->count()) {
            return [];
        }

        $definitions = ProductDefinition::query()->whereKey($definitionByProduct->values())->where('is_active', true)->get()->keyBy('id');

        if ($definitions->count() !== $definitionByProduct->unique()->count()) {
            return [];
        }

        $branches = [];

        foreach (Store::query()->orderBy('name')->get(['id', 'name', 'code']) as $store) {
            $lines = $this->linesAt($store->id, $quantities, $definitionByProduct, $definitions);

            if ($lines !== null) {
                $branches[] = [
                    'store_id' => $store->id,
                    'name' => $store->name,
                    'code' => $store->code,
                    'total_minor' => array_sum(array_map(fn (array $line): int => array_sum($line['unit_prices']), $lines)),
                    'lines' => $lines,
                ];
            }
        }

        usort($branches, fn (array $a, array $b): int => [$a['total_minor'], $a['name']] <=> [$b['total_minor'], $b['name']]);

        return $branches;
    }

    /**
     * @param  Collection<int, int>  $quantities
     * @param  Collection<int, int>  $definitionByProduct
     * @param  Collection<int, ProductDefinition>  $definitions
     * @return list<array{product_id: int, definition_id: int, tracking_mode: TrackingMode, quantity: int, unit_prices: list<int>}>|null
     */
    private function linesAt(int $storeId, Collection $quantities, Collection $definitionByProduct, Collection $definitions): ?array
    {
        $quantityIds = $definitions->filter(fn (ProductDefinition $definition): bool => $definition->tracking_mode === TrackingMode::QUANTITY)->keys()->all();
        $allocations = StorefrontAllocation::query()->where('store_id', $storeId)->whereIn('product_definition_id', $quantityIds)->whereNull('inventory_unit_id')->pluck('quantity', 'product_definition_id');
        $balances = InventoryBalance::query()->where('store_id', $storeId)->whereIn('product_definition_id', $quantityIds)->pluck('quantity', 'product_definition_id');
        $held = BranchOrderHolds::heldQuantities($storeId, $quantityIds);
        $lines = [];

        foreach ($quantities as $productId => $quantity) {
            $definition = $definitions->get($definitionByProduct->get($productId));

            if ($definition->tracking_mode === TrackingMode::QUANTITY) {
                $price = $definition->sellingPriceMinor();
                $available = min((int) ($allocations[$definition->id] ?? 0), (int) ($balances[$definition->id] ?? 0)) - ($held[$definition->id] ?? 0);

                if ($price === null || $available < $quantity) {
                    return null;
                }

                $prices = array_fill(0, $quantity, $price);
            } else {
                $prices = self::devicePrices($storeId, $definition)->take($quantity)->all();

                if (count($prices) < $quantity) {
                    return null;
                }
            }

            $lines[] = [
                'product_id' => (int) $productId,
                'definition_id' => $definition->id,
                'tracking_mode' => $definition->tracking_mode,
                'quantity' => $quantity,
                'unit_prices' => $prices,
            ];
        }

        return $lines;
    }

    /**
     * The lowest TRY price a linked product is offered at by any branch, for the cart's display;
     * the checkout shows each branch's exact price. Null when nothing is priced.
     */
    public static function fromPriceMinor(int $productId): ?int
    {
        $definition = ProductDefinition::query()->whereKey(StorefrontListing::query()->where('product_id', $productId)->value('product_definition_id'))->first();

        if ($definition === null) {
            return null;
        }

        if ($definition->tracking_mode !== TrackingMode::USED_DEVICE) {
            return $definition->sellingPriceMinor();
        }

        $lowest = InventoryUnit::query()
            ->from('inventory_units as units')
            ->join('storefront_allocations as allocations', 'allocations.inventory_unit_id', '=', 'units.id')
            ->whereColumn('allocations.store_id', 'units.store_id')
            ->where('allocations.quantity', '>', 0)
            ->where('units.product_definition_id', $definition->id)
            ->where('units.status', UnitStatus::AVAILABLE->value)
            ->whereNotNull('units.selling_price')
            ->min('units.selling_price');

        return $lowest === null ? null : Money::fromDecimal((string) $lowest);
    }

    /**
     * Prices of the branch's eligible allocated devices of one product, cheapest first.
     *
     * @return Collection<int, int>
     */
    public static function devicePrices(int $storeId, ProductDefinition $definition): Collection
    {
        return self::eligibleDevices($storeId, $definition)->get()
            ->map(fn (InventoryUnit $unit): int => SellingPrice::listPriceMinor($definition, $unit))
            ->sort()
            ->values();
    }

    /**
     * The branch's allocated devices of one product that a confirmation could hold now.
     *
     * @return Builder<InventoryUnit>
     */
    public static function eligibleDevices(int $storeId, ProductDefinition $definition): Builder
    {
        return InventoryUnit::query()
            ->select('units.*')
            ->from('inventory_units as units')
            ->join('product_definitions as definitions', 'definitions.id', '=', 'units.product_definition_id')
            ->join('storefront_allocations as allocations', 'allocations.inventory_unit_id', '=', 'units.id')
            ->where('units.store_id', $storeId)
            ->whereColumn('allocations.store_id', 'units.store_id')
            ->where('allocations.quantity', '>', 0)
            ->where('units.product_definition_id', $definition->id)
            ->where('units.status', UnitStatus::AVAILABLE->value)
            ->where('definitions.is_active', true)
            ->tap(fn (Builder $query) => ListingEligibility::wherePriced($query))
            ->tap(fn (Builder $query) => NetworkReservation::whereUnitNotHeld($query, 'units.id'))
            ->orderBy('units.id');
    }
}
