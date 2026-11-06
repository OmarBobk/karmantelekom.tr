<?php

declare(strict_types=1);

namespace App\Actions\Pos;

use App\Enums\TrackingMode;
use App\Enums\UnitStatus;
use App\Models\InventoryUnit;
use App\Models\ProductDefinition;
use App\Models\Store;
use App\Models\User;
use App\Services\Purchasing\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SetUnitSellingPrice
{
    /**
     * Set or clear a used device's individual selling price and its optional minimum price.
     *
     * The minimum is the lowest price an employee discount may reach for this device; it
     * replaces their general percentage here, and needs a selling price it does not exceed.
     *
     * Takes the inventory lock order (definition, then unit), so a price change serializes with
     * a checkout of the same device. The lifecycle revision is not advanced: a price is not a
     * lifecycle change, and open lifecycle forms must not be invalidated by it.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $actor, Store $store, int $unitId, ?string $price, ?string $minimumPrice): InventoryUnit
    {
        Gate::forUser($actor)->authorize('setUnitSellingPrices', $store);

        $priceMinor = SellingPrice::parse($price, 'selling_price');
        $minimumMinor = SellingPrice::parse($minimumPrice, 'minimum_selling_price');

        if ($minimumMinor !== null && $priceMinor === null) {
            throw ValidationException::withMessages(['minimum_selling_price' => __('pos.errors.minimum_needs_price')]);
        }

        if ($minimumMinor !== null && $minimumMinor > $priceMinor) {
            throw ValidationException::withMessages(['minimum_selling_price' => __('pos.errors.minimum_above_price')]);
        }

        $unit = $store->units()->whereKey($unitId)->firstOrFail();

        return DB::transaction(function () use ($actor, $store, $unit, $priceMinor, $minimumMinor): InventoryUnit {
            $definition = ProductDefinition::query()->whereKey($unit->product_definition_id)->lockForUpdate()->firstOrFail();
            $locked = $store->units()->whereKey($unit->id)->lockForUpdate()->firstOrFail();

            if ($definition->tracking_mode !== TrackingMode::USED_DEVICE) {
                throw ValidationException::withMessages(['selling_price' => __('pos.errors.unit_price_used_devices_only')]);
            }

            if (in_array($locked->status, [UnitStatus::SOLD, UnitStatus::REMOVED], true)) {
                throw ValidationException::withMessages(['selling_price' => __('pos.errors.unit_price_final_status', ['status' => $locked->status->label()])]);
            }

            $previous = $locked->selling_price;
            $previousMinimum = $locked->minimum_selling_price;
            $next = $priceMinor === null ? null : Money::toDecimal($priceMinor);
            $nextMinimum = $minimumMinor === null ? null : Money::toDecimal($minimumMinor);

            if ($previous === $next && $previousMinimum === $nextMinimum) {
                return $locked;
            }

            $locked->update(['selling_price' => $next, 'minimum_selling_price' => $nextMinimum]);

            activity('selling_price')
                ->performedOn($locked)
                ->causedBy($actor)
                ->event('unit_price_set')
                ->withProperties([
                    'store_id' => $store->id,
                    'old' => $previous,
                    'new' => $next,
                    'old_minimum' => $previousMinimum,
                    'new_minimum' => $nextMinimum,
                    'currency' => 'TRY',
                ])
                ->log("Selling price of unit #{$locked->id} in {$store->code} set to ".($next ?? 'not priced').($nextMinimum === null ? '' : ", minimum {$nextMinimum}"));

            return $locked;
        });
    }
}
