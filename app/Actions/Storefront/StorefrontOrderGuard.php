<?php

declare(strict_types=1);

namespace App\Actions\Storefront;

use App\Models\Product;
use App\Models\StorefrontListing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Linked branch stock is display-only until branch confirmation and stock holds exist, so no
 * storefront path may cart or order it. Products the publisher workspace created stay
 * display-only after unlinking. Unlinked legacy products order as before.
 */
final class StorefrontOrderGuard
{
    /**
     * The given storefront products that cannot be carted or ordered.
     *
     * @param  iterable<int|string>  $productIds
     * @return list<int>
     */
    public static function displayOnlyProductIds(iterable $productIds): array
    {
        $ids = self::normalize($productIds);

        if ($ids->isEmpty()) {
            return [];
        }

        return self::displayOnlyAmong($ids, Product::query()->whereIn('id', $ids)->where('storefront_managed', true)->pluck('id'), StorefrontListing::query());
    }

    /**
     * Unlocked check for carts and early refusal; order writes must use lockOrderable().
     *
     * @param  iterable<int|string>  $productIds
     *
     * @throws ValidationException
     */
    public static function assertOrderable(iterable $productIds, string $key = 'cart'): void
    {
        if (self::displayOnlyProductIds($productIds) !== []) {
            throw ValidationException::withMessages([$key => __('main.storefront_display_only')]);
        }
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
            throw ValidationException::withMessages([$key => __('main.storefront_display_only')]);
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
