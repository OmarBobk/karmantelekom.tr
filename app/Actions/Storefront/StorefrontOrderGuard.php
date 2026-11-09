<?php

declare(strict_types=1);

namespace App\Actions\Storefront;

use App\Models\StorefrontListing;
use Illuminate\Validation\ValidationException;

/**
 * Linked branch stock is display-only until branch confirmation and stock holds exist, so no
 * storefront path may cart or order it. Unlinked legacy products order as before.
 */
final class StorefrontOrderGuard
{
    /**
     * @param  iterable<int|string>  $productIds
     *
     * @throws ValidationException
     */
    public static function assertOrderable(iterable $productIds, string $key = 'cart'): void
    {
        if (StorefrontListing::linkedProductIds($productIds) !== []) {
            throw ValidationException::withMessages([$key => __('main.storefront_display_only')]);
        }
    }
}
