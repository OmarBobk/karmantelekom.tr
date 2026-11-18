<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Enums\TrackingMode;
use App\Models\InventoryStockThreshold;
use App\Models\ProductDefinition;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Set or clear the optional low-stock threshold for one product at one branch.
 *
 * Does not change stock quantities or write inventory movements.
 */
final class SetStockThreshold
{
    /**
     * @param  string|null  $threshold  Positive whole number to set, or null/blank to clear.
     *
     * @throws ValidationException
     */
    public function __invoke(
        User $actor,
        Store $store,
        ProductDefinition $definition,
        ?string $threshold,
    ): ?InventoryStockThreshold {
        Gate::forUser($actor)->authorize('viewInventory', $store);
        Gate::forUser($actor)->authorize('managePurchases', $store);

        $locked = ProductDefinition::query()->findOrFail($definition->id);

        if ($locked->tracking_mode === TrackingMode::USED_DEVICE) {
            throw ValidationException::withMessages([
                'threshold' => __('inventory.threshold.errors.used_device'),
            ]);
        }

        $value = $this->parseThreshold($threshold);

        if ($value === null) {
            InventoryStockThreshold::query()
                ->where('store_id', $store->id)
                ->where('product_definition_id', $locked->id)
                ->delete();

            return null;
        }

        return InventoryStockThreshold::query()->updateOrCreate(
            [
                'store_id' => $store->id,
                'product_definition_id' => $locked->id,
            ],
            ['threshold' => $value],
        );
    }

    /**
     * @throws ValidationException
     */
    private function parseThreshold(?string $threshold): ?int
    {
        $raw = Str::trim((string) $threshold);

        if ($raw === '') {
            return null;
        }

        if (! preg_match('/^\d+$/', $raw) || (int) $raw < 1) {
            throw ValidationException::withMessages([
                'threshold' => __('inventory.threshold.errors.invalid'),
            ]);
        }

        return (int) $raw;
    }
}
