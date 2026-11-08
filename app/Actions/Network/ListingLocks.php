<?php

declare(strict_types=1);

namespace App\Actions\Network;

use App\Models\InventoryUnit;
use App\Models\NetworkListing;
use App\Models\ProductDefinition;
use App\Models\Store;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The lock order every listing change takes: product definition, device, then listing. The
 * device is re-read under its lock through the branch, so a device that left it is not found.
 */
final class ListingLocks
{
    /**
     * @template TResult
     *
     * @param  Closure(ProductDefinition, InventoryUnit, ?NetworkListing): TResult  $change
     * @return TResult
     */
    public static function run(Store $store, int $unitId, Closure $change): mixed
    {
        $definitionId = $store->units()->whereKey($unitId)->firstOrFail()->product_definition_id;

        return DB::transaction(function () use ($store, $unitId, $definitionId, $change): mixed {
            $definition = ProductDefinition::query()->whereKey($definitionId)->lockForUpdate()->firstOrFail();
            $unit = $store->units()->whereKey($unitId)->where('product_definition_id', $definition->id)->lockForUpdate()->firstOrFail();
            $listing = NetworkListing::query()->where('inventory_unit_id', $unit->id)->lockForUpdate()->first();

            return $change($definition, $unit, $listing);
        });
    }

    /**
     * @throws ValidationException
     */
    public static function assertCurrent(?NetworkListing $listing, int $expectedRevision): void
    {
        if (NetworkListing::revisionOf($listing) !== $expectedRevision) {
            throw ValidationException::withMessages(['listing' => __('network.errors.stale')]);
        }
    }
}
