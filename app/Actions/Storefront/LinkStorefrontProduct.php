<?php

declare(strict_types=1);

namespace App\Actions\Storefront;

use App\Models\Product;
use App\Models\ProductDefinition;
use App\Models\StorefrontListing;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class LinkStorefrontProduct
{
    /**
     * Link an operational product to an existing storefront product chosen by the publisher.
     *
     * The storefront product is only referenced: its URLs, images, translations, prices, and
     * order history are untouched. The link starts unpublished on both channels. Linking the same
     * pair again returns the link unchanged; either side already linked elsewhere is refused.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $actor, int $definitionId, int $productId): StorefrontListing
    {
        StorefrontPublishing::authorize($actor);

        return DB::transaction(function () use ($actor, $definitionId, $productId): StorefrontListing {
            $definition = ProductDefinition::query()->whereKey($definitionId)->lockForUpdate()->firstOrFail();
            $product = Product::query()->whereKey($productId)->lockForUpdate()->first();

            if ($product === null) {
                throw ValidationException::withMessages(['link' => __('storefront.errors.product_missing')]);
            }

            $existing = StorefrontListing::query()->where('product_definition_id', $definition->id)->lockForUpdate()->first();

            if ($existing !== null) {
                if ($existing->product_id === $product->id) {
                    return $existing;
                }

                throw ValidationException::withMessages(['link' => __('storefront.errors.already_linked')]);
            }

            if (StorefrontListing::query()->where('product_id', $product->id)->exists()) {
                throw ValidationException::withMessages(['link' => __('storefront.errors.product_taken')]);
            }

            StorefrontPublishing::assertAllocated($definition, 'link');

            $listing = StorefrontListing::query()->create([
                'product_definition_id' => $definition->id,
                'product_id' => $product->id,
                'linked_by' => $actor->id,
            ]);

            StorefrontPublishing::audit($listing, $actor, 'listing_linked', "Product #{$definition->id} linked to storefront product {$product->code}");

            return $listing;
        });
    }
}
