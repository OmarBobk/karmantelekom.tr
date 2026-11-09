<?php

declare(strict_types=1);

namespace App\Actions\Storefront;

use App\Models\ProductDefinition;
use App\Models\StorefrontAllocation;
use App\Models\StorefrontListing;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Checks shared by the publisher actions.
 */
final class StorefrontPublishing
{
    /**
     * @throws AuthorizationException
     */
    public static function authorize(User $actor): void
    {
        Gate::forUser($actor)->authorize('publish', StorefrontListing::class);
    }

    /**
     * Linking and publishing need stock some branch made eligible; withdrawing never does.
     *
     * @throws ValidationException
     */
    public static function assertAllocated(ProductDefinition $definition, string $key): void
    {
        if (! StorefrontAllocation::query()->where('product_definition_id', $definition->id)->where('quantity', '>', 0)->exists()) {
            throw ValidationException::withMessages([$key => __('storefront.errors.not_allocated')]);
        }
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    public static function audit(StorefrontListing $listing, User $actor, string $event, string $description, array $properties = []): void
    {
        activity('storefront')
            ->performedOn($listing)
            ->causedBy($actor)
            ->event($event)
            ->withProperties(['product_definition_id' => $listing->product_definition_id, 'product_id' => $listing->product_id, ...$properties])
            ->log($description);
    }
}
