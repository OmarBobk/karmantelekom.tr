<?php

declare(strict_types=1);

namespace App\Actions\Storefront;

use App\Enums\StorefrontChannel;
use App\Models\Product;
use App\Models\StorefrontListing;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Linked branch stock is ordered only through a branch order (SubmitBranchOrder), which a branch
 * confirms with stock holds. While storefront branch orders are switched off it is display-only,
 * and the legacy order paths (checkout for legacy carts, Order now) never take it. Products the
 * publisher workspace created stay display-only after unlinking. Unlinked legacy products order
 * as before.
 */
final class StorefrontOrderGuard
{
    /**
     * The given storefront products that cannot be carted. With a signed-in buyer and branch
     * orders switched on, linked products published to a channel the buyer may see, with a price
     * in that channel, can be carted for a branch order.
     *
     * @param  iterable<int|string>  $productIds
     * @return list<int>
     */
    public static function displayOnlyProductIds(iterable $productIds, ?User $buyer = null): array
    {
        $ids = self::normalize($productIds);

        if ($ids->isEmpty()) {
            return [];
        }

        $displayOnly = self::displayOnlyAmong($ids, Product::query()->whereIn('id', $ids)->where('storefront_managed', true)->pluck('id'), StorefrontListing::query());

        if ($displayOnly === [] || $buyer === null || ! BranchOrderHolds::enabled()) {
            return $displayOnly;
        }

        $branchOrderable = Product::query()->whereIn('id', $displayOnly)->visibleInChannels(StorefrontChannel::audienceOf($buyer))
            ->whereHas('storefrontListing')->pluck('id')->map(fn (int|string $id): int => (int) $id)
            ->filter(fn (int $id): bool => StorefrontPricing::hasChannelPrice($id, StorefrontPricing::orderChannel($buyer, [$id])))
            ->all();

        return array_values(array_diff($displayOnly, $branchOrderable));
    }

    /**
     * Unlocked check for carts and early refusal; order writes must use lockOrderable() or
     * SubmitBranchOrder.
     *
     * @param  iterable<int|string>  $productIds
     *
     * @throws ValidationException
     */
    public static function assertOrderable(iterable $productIds, string $key = 'cart', ?User $buyer = null): void
    {
        if (self::displayOnlyProductIds($productIds, $buyer) !== []) {
            throw ValidationException::withMessages([$key => self::refusal()]);
        }
    }

    /**
     * Why a legacy order path refuses linked stock.
     */
    public static function refusal(): string
    {
        return BranchOrderHolds::enabled() ? __('main.branch_orders.errors.use_checkout') : __('main.storefront_display_only');
    }

    /**
     * Lock the ordered products in id order and recheck them, inside the order's transaction.
     *
     * Linking locks the product row before writing the link, so an order and a link on the same
     * product serialize. The link is read with a locking read, which sees a link committed while
     * this transaction waited, whatever its snapshot.
     *
     * @param  iterable<int|string>  $productIds
     *
     * @throws ValidationException
     */
    public static function lockOrderable(iterable $productIds, string $key = 'cart'): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Lock storefront products inside the order transaction.');
        }

        $ids = self::normalize($productIds)->sort()->values();

        if ($ids->isEmpty()) {
            return;
        }

        $managed = Product::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get(['id', 'storefront_managed'])
            ->filter(fn (Product $product): bool => $product->storefront_managed)
            ->pluck('id');

        if (self::displayOnlyAmong($ids, $managed, StorefrontListing::query()->sharedLock()) !== []) {
            throw ValidationException::withMessages([$key => self::refusal()]);
        }
    }

    /**
     * @param  Collection<int, int>  $ids
     * @param  Collection<int, int|string>  $managed
     * @param  Builder<StorefrontListing>  $listings
     * @return list<int>
     */
    private static function displayOnlyAmong(Collection $ids, Collection $managed, Builder $listings): array
    {
        return $listings->whereIn('product_id', $ids)->pluck('product_id')
            ->merge($managed)
            ->map(fn (int|string $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  iterable<int|string>  $productIds
     * @return Collection<int, int>
     */
    private static function normalize(iterable $productIds): Collection
    {
        return collect($productIds)->map(fn (int|string $id): int => (int) $id)->filter()->unique()->values();
    }
}
