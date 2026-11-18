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
    /** Matches the unsignedInteger column on inventory_stock_thresholds.threshold. */
    public const MAX_THRESHOLD = 4_294_967_295;

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

        if (! preg_match('/^\d+$/', $raw)) {
            throw ValidationException::withMessages([
                'threshold' => __('inventory.threshold.errors.invalid'),
            ]);
        }

        // Compare as a digit string before casting so values above unsigned int never wrap.
        $normalized = ltrim($raw, '0');

        if ($normalized === '') {
            throw ValidationException::withMessages([
                'threshold' => __('inventory.threshold.errors.invalid'),
            ]);
        }

        if (strlen($normalized) > 10
            || (strlen($normalized) === 10 && strcmp($normalized, (string) self::MAX_THRESHOLD) > 0)) {
            throw ValidationException::withMessages([
                'threshold' => __('inventory.threshold.errors.too_large', ['max' => self::MAX_THRESHOLD]),
            ]);
        }

        return (int) $normalized;
    }
}
