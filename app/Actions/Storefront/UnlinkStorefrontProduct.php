<?php

declare(strict_types=1);

namespace App\Actions\Storefront;

use App\Models\StorefrontListing;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UnlinkStorefrontProduct
{
    /**
     * Remove a wrong link once it is withdrawn from both channels. The storefront product and the
     * operational product are both kept; the audit event records the pair. A missing link is a no-op.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $actor, int $listingId): void
    {
        StorefrontPublishing::authorize($actor);

        DB::transaction(function () use ($actor, $listingId): void {
            $listing = StorefrontListing::query()->whereKey($listingId)->lockForUpdate()->first();

            if ($listing === null) {
                return;
            }

            if ($listing->isPublished()) {
                throw ValidationException::withMessages(['link' => __('storefront.errors.unlink_published')]);
            }

            StorefrontPublishing::audit($listing, $actor, 'listing_unlinked', "Product #{$listing->product_definition_id} unlinked from storefront product #{$listing->product_id}");
            $listing->delete();
        });
    }
}
