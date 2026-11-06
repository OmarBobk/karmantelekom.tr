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
     * Set or clear a used device's individual selling price.
     *
     * Takes the inventory lock order (definition, then unit), so a price change serializes with
     * a checkout of the same device. The lifecycle revision is not advanced: a price is not a
     * lifecycle change, and open lifecycle forms must not be invalidated by it.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $actor, Store $store, int $unitId, ?string $price): InventoryUnit
    {
        Gate::forUser($actor)->authorize('setUnitSellingPrices', $store);

        $priceMinor = SellingPrice::parse($price, 'selling_price');
        $unit = $store->units()->whereKey($unitId)->firstOrFail();

        return DB::transaction(function () use ($actor, $store, $unit, $priceMinor): InventoryUnit {
            $definition = ProductDefinition::query()->whereKey($unit->product_definition_id)->lockForUpdate()->firstOrFail();
            $locked = $store->units()->whereKey($unit->id)->lockForUpdate()->firstOrFail();

            if ($definition->tracking_mode !== TrackingMode::USED_DEVICE) {
                throw ValidationException::withMessages(['selling_price' => __('pos.errors.unit_price_used_devices_only')]);
            }

            if (in_array($locked->status, [UnitStatus::SOLD, UnitStatus::REMOVED], true)) {
                throw ValidationException::withMessages(['selling_price' => __('pos.errors.unit_price_final_status', ['status' => $locked->status->label()])]);
            }

            $previous = $locked->selling_price;
            $next = $priceMinor === null ? null : Money::toDecimal($priceMinor);

            if ($previous === $next) {
                return $locked;
            }

            $locked->update(['selling_price' => $next]);

            activity('selling_price')
                ->performedOn($locked)
                ->causedBy($actor)
                ->event('unit_price_set')
                ->withProperties(['store_id' => $store->id, 'old' => $previous, 'new' => $next, 'currency' => 'TRY'])
                ->log("Selling price of unit #{$locked->id} in {$store->code} set to ".($next ?? 'not priced'));

            return $locked;
        });
    }
}
