<?php

declare(strict_types=1);

namespace App\Actions\Storefront;

use App\Actions\Network\ListingEligibility;
use App\Actions\Pos\SellingPrice;
use App\Enums\StorefrontChannel;
use App\Enums\TrackingMode;
use App\Enums\UnitStatus;
use App\Models\InventoryBalance;
use App\Models\InventoryUnit;
use App\Models\NetworkReservation;
use App\Models\ProductDefinition;
use App\Models\Store;
use App\Models\StorefrontAllocation;
use App\Models\StorefrontListing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Which kind of order a cart makes and, for linked branch stock, the branches that can fulfill
 * the whole cart now with their server prices in TRY for the order's channel. Read-only and
 * unlocked: submission repeats it, and nothing is held until a branch confirms.
 *
 * Quantity stock counts the branch's online allocation, up to its balance, less what confirmed
 * orders hold. Devices count while allocated, in the branch, available, priced, and not held by
 * a reservation or order. Prices come from StorefrontPricing. A used device line is priced at
 * the branch's cheapest eligible devices; the branch picks the exact device at confirmation, and
 * a different price needs the buyer's acceptance.
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
     * Products in the cart without a published price in the channel for their quantity; such a
     * cart cannot be ordered until the price is set. Used devices are priced per device.
     *
     * @param  Collection<int, int>  $quantities  Cart quantity by storefront product id; all linked.
     * @return list<int>
     */
    public static function priceGaps(Collection $quantities, StorefrontChannel $channel): array
    {
        $modes = StorefrontListing::query()->whereIn('storefront_listings.product_id', $quantities->keys())
            ->join('product_definitions', 'product_definitions.id', '=', 'storefront_listings.product_definition_id')
            ->pluck('product_definitions.tracking_mode', 'storefront_listings.product_id');
        $gaps = [];

        foreach ($quantities as $productId => $quantity) {
            $mode = $modes->get($productId);
            $mode = $mode instanceof TrackingMode ? $mode : TrackingMode::tryFrom((string) $mode);

            if ($mode !== null && ! SellingPrice::usesUnitPrice($mode) && StorefrontPricing::channelPriceMinor((int) $productId, $channel, (int) $quantity) === null) {
                $gaps[] = (int) $productId;
            }
        }

        return $gaps;
    }

    /**
     * Branches that can fulfill every line, cheapest first.
     *
     * @param  Collection<int, int>  $quantities  Cart quantity by storefront product id; all linked.
     * @return list<array{store_id: int, name: string, code: string, total_minor: int, lines: list<array{product_id: int, definition_id: int, tracking_mode: TrackingMode, quantity: int, unit_prices: list<int>}>}>
     */
    public function branches(Collection $quantities, StorefrontChannel $channel): array
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

        $channelPrices = [];

        foreach ($quantities as $productId => $quantity) {
            $definition = $definitions->get($definitionByProduct->get($productId));

            if (! SellingPrice::usesUnitPrice($definition->tracking_mode)) {
                $channelPrices[$productId] = StorefrontPricing::channelPriceMinor((int) $productId, $channel, (int) $quantity);

                if ($channelPrices[$productId] === null) {
                    return [];
                }
            }
        }

        $branches = [];

        foreach (Store::query()->orderBy('name')->get(['id', 'name', 'code']) as $store) {
            $lines = $this->linesAt($store->id, $quantities, $definitionByProduct, $definitions, $channelPrices);

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
     * @param  array<int, int>  $channelPrices  Unit price by product id, for products not priced per device.
     * @return list<array{product_id: int, definition_id: int, tracking_mode: TrackingMode, quantity: int, unit_prices: list<int>}>|null
     */
    private function linesAt(int $storeId, Collection $quantities, Collection $definitionByProduct, Collection $definitions, array $channelPrices): ?array
    {
        $quantityIds = $definitions->filter(fn (ProductDefinition $definition): bool => $definition->tracking_mode === TrackingMode::QUANTITY)->keys()->all();
        $allocations = StorefrontAllocation::query()->where('store_id', $storeId)->whereIn('product_definition_id', $quantityIds)->whereNull('inventory_unit_id')->pluck('quantity', 'product_definition_id');
        $balances = InventoryBalance::query()->where('store_id', $storeId)->whereIn('product_definition_id', $quantityIds)->pluck('quantity', 'product_definition_id');
        $held = BranchOrderHolds::heldQuantities($storeId, $quantityIds);
        $lines = [];

        foreach ($quantities as $productId => $quantity) {
            $definition = $definitions->get($definitionByProduct->get($productId));

            if ($definition->tracking_mode === TrackingMode::QUANTITY) {
                $available = min((int) ($allocations[$definition->id] ?? 0), (int) ($balances[$definition->id] ?? 0)) - ($held[$definition->id] ?? 0);

                if ($available < $quantity) {
                    return null;
                }

                $prices = array_fill(0, $quantity, $channelPrices[$productId]);
            } elseif (SellingPrice::usesUnitPrice($definition->tracking_mode)) {
                $prices = self::devicePrices($storeId, $definition)->take($quantity)->all();

                if (count($prices) < $quantity) {
                    return null;
                }
            } else {
                if (self::eligibleDevices($storeId, $definition)->count() < $quantity) {
                    return null;
                }

                $prices = array_fill(0, $quantity, $channelPrices[$productId]);
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
     * Prices of the branch's eligible allocated used devices of one product, cheapest first.
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
     * Allocated devices of one product that a confirmation could hold now, at one branch or at
     * any branch when no branch is given.
     *
     * @return Builder<InventoryUnit>
     */
    public static function eligibleDevices(?int $storeId, ProductDefinition $definition): Builder
    {
        return InventoryUnit::query()
            ->select('units.*')
            ->from('inventory_units as units')
            ->join('product_definitions as definitions', 'definitions.id', '=', 'units.product_definition_id')
            ->join('storefront_allocations as allocations', 'allocations.inventory_unit_id', '=', 'units.id')
            ->when($storeId !== null, fn (Builder $query) => $query->where('units.store_id', $storeId))
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
