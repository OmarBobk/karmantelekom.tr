<?php

declare(strict_types=1);

namespace App\Actions\Storefront;

use App\Models\ProductDefinition;
use App\Models\Store;
use App\Models\StorefrontAllocation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class SetStorefrontQuantity
{
    /**
     * Set how many units of a quantity-tracked product the branch offers online. Zero withdraws it.
     *
     * The quantity is a ceiling the storefront shows against current stock; it may not exceed the
     * branch's on-hand quantity when set, and no stock movement is written. Setting the current
     * value changes nothing and writes no audit event, so retries are harmless.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $actor, Store $store, int $definitionId, int $quantity): ?StorefrontAllocation
    {
        Gate::forUser($actor)->authorize('allocateStorefrontStock', $store);

        if ($quantity < 0 || $quantity > StorefrontAllocation::MAX_QUANTITY) {
            throw ValidationException::withMessages(['allocation' => __('storefront.errors.quantity_range', ['max' => StorefrontAllocation::MAX_QUANTITY])]);
        }

        return DB::transaction(function () use ($actor, $store, $definitionId, $quantity): ?StorefrontAllocation {
            $definition = ProductDefinition::query()->whereKey($definitionId)->lockForUpdate()->firstOrFail();
            $allocation = StorefrontAllocation::query()
                ->where('store_id', $store->id)
                ->where('product_definition_id', $definition->id)
                ->whereNull('inventory_unit_id')
                ->lockForUpdate()
                ->first();
            $previous = $allocation->quantity ?? 0;

            if ($previous === $quantity) {
                return $allocation;
            }

            if ($quantity > 0) {
                $onHand = (int) ($store->inventoryBalances()->where('product_definition_id', $definition->id)->value('quantity') ?? 0);
                $problem = StorefrontEligibility::quantityProblem($definition, $quantity, $onHand);

                if ($problem !== null) {
                    throw ValidationException::withMessages(['allocation' => $problem]);
                }
            }

            if ($allocation === null) {
                $allocation = StorefrontAllocation::query()->create([
                    'store_id' => $store->id,
                    'product_definition_id' => $definition->id,
                    'quantity' => $quantity,
                    'updated_by' => $actor->id,
                ]);
            } else {
                $allocation->update(['quantity' => $quantity, 'updated_by' => $actor->id]);
            }

            activity('storefront')
                ->performedOn($allocation)
                ->causedBy($actor)
                ->event('allocation_set')
                ->withProperties(['store_id' => $store->id, 'product_definition_id' => $definition->id, 'from' => $previous, 'to' => $quantity])
                ->log("Online quantity of product #{$definition->id} at {$store->code} set from {$previous} to {$quantity}");

            return $allocation;
        });
    }
}
