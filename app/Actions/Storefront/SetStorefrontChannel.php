<?php

declare(strict_types=1);

namespace App\Actions\Storefront;

use App\Enums\StorefrontChannel;
use App\Models\StorefrontListing;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SetStorefrontChannel
{
    /**
     * Publish a linked product to one channel, or withdraw it. Both channels share one allocation.
     *
     * Publishing needs allocated stock and an active storefront product; wholesale also needs the
     * active price tiers its catalog already requires. Prices stay with the legacy product admin.
     * Setting the current state changes nothing and writes no audit event.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $actor, int $listingId, StorefrontChannel $channel, bool $publish): StorefrontListing
    {
        StorefrontPublishing::authorize($actor);

        return DB::transaction(function () use ($actor, $listingId, $channel, $publish): StorefrontListing {
            $listing = StorefrontListing::query()->whereKey($listingId)->lockForUpdate()->firstOrFail();

            if ($listing->isPublishedTo($channel) === $publish) {
                return $listing;
            }

            if ($publish) {
                StorefrontPublishing::assertAllocated($listing->productDefinition()->firstOrFail(), 'publish');
                $product = $listing->product()->firstOrFail();

                if (! $product->is_active) {
                    throw ValidationException::withMessages(['publish' => __('storefront.errors.product_hidden')]);
                }

                if ($channel === StorefrontChannel::WHOLESALE && ! $product->priceTiers()->where('is_active', true)->exists()) {
                    throw ValidationException::withMessages(['publish' => __('storefront.errors.no_wholesale_tiers')]);
                }
            }

            $listing->update([$channel->column() => $publish ? now() : null]);

            StorefrontPublishing::audit(
                $listing,
                $actor,
                $publish ? 'listing_published' : 'listing_withdrawn',
                'Storefront product #'.$listing->product_id.($publish ? ' published to ' : ' withdrawn from ').$channel->value,
                ['channel' => $channel->value],
            );

            return $listing;
        });
    }
}
