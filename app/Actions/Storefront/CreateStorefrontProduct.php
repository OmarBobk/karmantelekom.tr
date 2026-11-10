<?php

declare(strict_types=1);

namespace App\Actions\Storefront;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductDefinition;
use App\Models\StorefrontListing;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateStorefrontProduct
{
    /**
     * Create a storefront product for an operational product with no storefront match, and link it.
     *
     * The new product takes the operational name and description and the chosen storefront
     * category. Its code is the SKU, or OP-{id} when there is none or it is taken. It has no
     * prices or images: those stay with the legacy product admin and its pricing permissions.
     * The link starts unpublished. A product that is already linked is refused. The product is
     * marked storefront-managed, so it stays hidden and unorderable if it is later unlinked.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $actor, int $definitionId, ?int $categoryId): StorefrontListing
    {
        StorefrontPublishing::authorize($actor);

        if ($categoryId === null || ! Category::query()->whereKey($categoryId)->exists()) {
            throw ValidationException::withMessages(['category' => __('storefront.errors.category_required')]);
        }

        return DB::transaction(function () use ($actor, $definitionId, $categoryId): StorefrontListing {
            $definition = ProductDefinition::query()->whereKey($definitionId)->lockForUpdate()->firstOrFail();

            if (StorefrontListing::query()->where('product_definition_id', $definition->id)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['link' => __('storefront.errors.already_linked')]);
            }

            StorefrontPublishing::assertAllocated($definition, 'link');

            $code = collect([$definition->sku, 'OP-'.$definition->id])
                ->filter()
                ->first(fn (string $candidate): bool => ! Product::query()->where('code', $candidate)->exists());

            if ($code === null) {
                throw ValidationException::withMessages(['link' => __('storefront.errors.code_taken')]);
            }

            $product = Product::query()->create([
                'name' => trim($definition->name.' '.($definition->model ?? '')),
                'code' => $code,
                'description' => $definition->description ?? '',
                'category_id' => $categoryId,
                'is_active' => true,
                'storefront_managed' => true,
            ]);

            $listing = StorefrontListing::query()->create([
                'product_definition_id' => $definition->id,
                'product_id' => $product->id,
                'linked_by' => $actor->id,
            ]);

            StorefrontPublishing::audit($listing, $actor, 'listing_created', "Storefront product {$product->code} created for product #{$definition->id}");

            return $listing;
        });
    }
}
