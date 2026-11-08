<?php

declare(strict_types=1);

namespace App\Actions\Network;

use App\Actions\Pos\SellingPrice;
use App\Enums\TrackingMode;
use App\Enums\UnitStatus;
use App\Models\InventoryUnit;
use App\Models\ProductDefinition;

/**
 * Whether a device may be shared on Store Network: the same rules that let it be sold at the
 * counter. An individually tracked product that is active, a device available in stock, and a
 * selling price. SharedListings applies the same conditions when it lists devices.
 */
final class ListingEligibility
{
    /**
     * The reason the device cannot be listed, or null when it can.
     */
    public static function problem(ProductDefinition $definition, InventoryUnit $unit): ?string
    {
        return match (true) {
            $definition->tracking_mode === TrackingMode::QUANTITY => __('network.errors.not_individual'),
            ! $definition->is_active => __('network.errors.product_inactive'),
            $unit->status !== UnitStatus::AVAILABLE => __('network.errors.unit_unavailable', ['status' => $unit->status->label()]),
            SellingPrice::listPriceMinor($definition, $unit) === null => __('network.errors.not_priced'),
            default => null,
        };
    }
}
