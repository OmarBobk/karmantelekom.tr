<?php

declare(strict_types=1);

namespace App\Actions\Storefront;

use App\Actions\Pos\SellingPrice;
use App\Enums\StorefrontChannel;
use App\Enums\TrackingMode;
use App\Models\InventoryUnit;
use App\Models\ProductDefinition;
use App\Models\StorefrontListing;
use App\Models\User;
use App\Services\Purchasing\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Prices of linked branch stock on the storefront, in TRY, as published for a sales channel.
 * The storefront, the cart, the checkout quote, submission, confirmation, and price proposals
 * all price through this class.
 *
 * Retail uses the storefront product's TRY price. Wholesale uses its active TRY tier whose
 * quantity range covers the ordered quantity. A used device is priced individually at the
 * device's own selling price in either channel. A product without such a price cannot be
 * ordered in that channel. An order is bound to one channel: wholesale when the buyer may see
 * wholesale and every product is published there, otherwise retail when every product is
 * published to retail.
 */
final class StorefrontPricing
{
    /**
     * The channel an order for these products is bound to, or null when the buyer may not order
     * them together. Pass the listings read under the caller's locks when it holds them.
     *
     * @param  iterable<int|string>  $productIds
     * @param  Collection<int, StorefrontListing>|null  $listings  Listings keyed by product id.
     */
    public static function orderChannel(?User $buyer, iterable $productIds, ?Collection $listings = null): ?StorefrontChannel
    {
        $ids = collect($productIds)->map(fn (int|string $id): int => (int) $id)->unique()->values();
        $listings ??= StorefrontListing::query()->whereIn('product_id', $ids)->get()->keyBy('product_id');

        if ($ids->isEmpty() || $ids->contains(fn (int $id): bool => ! $listings->has($id))) {
            return null;
        }

        foreach (StorefrontChannel::audienceOf($buyer) === [StorefrontChannel::RETAIL] ? [StorefrontChannel::RETAIL] : [StorefrontChannel::WHOLESALE, StorefrontChannel::RETAIL] as $channel) {
            if ($ids->every(fn (int $id): bool => $listings->get($id)->isPublishedTo($channel))) {
                return $channel;
            }
        }

        return null;
    }

    /**
     * The published TRY unit price of a storefront product in a channel for the ordered quantity,
     * or null when the channel has no such price. Not for used devices, which are priced per device.
     */
    public static function channelPriceMinor(int $productId, StorefrontChannel $channel, int $quantity): ?int
    {
        $price = $channel === StorefrontChannel::RETAIL
            ? DB::table('product_prices')
                ->join('currencies', 'currencies.id', '=', 'product_prices.currency_id')
                ->where('product_prices.product_id', $productId)
                ->where('currencies.code', 'TRY')
                ->where('product_prices.converted_price', '>', 0)
                ->value('product_prices.converted_price')
            : DB::table('wholesale_products')
                ->join('currencies', 'currencies.id', '=', 'wholesale_products.currency_id')
                ->where('wholesale_products.product_id', $productId)
                ->where('currencies.code', 'TRY')
                ->where('wholesale_products.is_active', true)
                ->where('wholesale_products.price', '>', 0)
                ->where('wholesale_products.min_qty', '<=', $quantity)
                ->where('wholesale_products.max_qty', '>=', $quantity)
                ->orderByDesc('wholesale_products.min_qty')
                ->orderBy('wholesale_products.price')
                ->value('wholesale_products.price');

        return $price === null ? null : Money::fromDecimal((string) $price);
    }

    /**
     * The price of one ordered item: a used device at its own price, anything else at the
     * channel price for the product's total ordered quantity.
     */
    public static function itemPriceMinor(int $productId, ProductDefinition $definition, ?InventoryUnit $unit, StorefrontChannel $channel, int $productQuantity): ?int
    {
        if (SellingPrice::usesUnitPrice($definition->tracking_mode)) {
            return $unit === null ? null : SellingPrice::listPriceMinor($definition, $unit);
        }

        return self::channelPriceMinor($productId, $channel, $productQuantity);
    }

    /**
     * Whether a linked product can be priced in the channel: used devices always can, per device;
     * other products need a retail price or an active wholesale tier.
     */
    public static function hasChannelPrice(int $productId, ?StorefrontChannel $channel): bool
    {
        if ($channel === null) {
            return false;
        }

        $mode = DB::table('storefront_listings')
            ->join('product_definitions', 'product_definitions.id', '=', 'storefront_listings.product_definition_id')
            ->where('storefront_listings.product_id', $productId)
            ->value('product_definitions.tracking_mode');

        if ($mode === null) {
            return false;
        }

        return SellingPrice::usesUnitPrice(TrackingMode::from((string) $mode)) || self::fromPriceMinor($productId, $channel) !== null;
    }

    /**
     * The lowest price to show for a linked product in a channel before a branch is chosen: the
     * cheapest eligible device in any branch for a used device, the retail price, or the lowest
     * active wholesale tier. Null when the product cannot be ordered in the channel.
     */
    public static function fromPriceMinor(int $productId, StorefrontChannel $channel): ?int
    {
        $definition = ProductDefinition::query()->whereKey(StorefrontListing::query()->where('product_id', $productId)->value('product_definition_id'))->first();

        if ($definition === null) {
            return null;
        }

        if ($definition->tracking_mode === TrackingMode::USED_DEVICE) {
            $lowest = QuoteBranchOrder::eligibleDevices(null, $definition)->min('units.selling_price');

            return $lowest === null ? null : Money::fromDecimal((string) $lowest);
        }

        if ($channel === StorefrontChannel::RETAIL) {
            return self::channelPriceMinor($productId, $channel, 1);
        }

        $lowest = DB::table('wholesale_products')
            ->join('currencies', 'currencies.id', '=', 'wholesale_products.currency_id')
            ->where('wholesale_products.product_id', $productId)
            ->where('currencies.code', 'TRY')
            ->where('wholesale_products.is_active', true)
            ->where('wholesale_products.price', '>', 0)
            ->min('wholesale_products.price');

        return $lowest === null ? null : Money::fromDecimal((string) $lowest);
    }
}
