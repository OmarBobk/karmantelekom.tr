<?php

declare(strict_types=1);

namespace App\Actions\Storefront;

use App\Actions\Pos\SellingPrice;
use App\Enums\TrackingMode;
use App\Enums\UnitStatus;
use App\Models\InventoryUnit;
use App\Models\ProductDefinition;

/**
 * Whether branch stock may be made eligible for the storefront: the same conditions that let it
 * be sold at the counter. StorefrontAvailability applies them again on every storefront read.
 */
final class StorefrontEligibility
{
    /**
     * The reason an online quantity cannot be set, or null when it can.
     */
    public static function quantityProblem(ProductDefinition $definition, int $quantity, int $onHand): ?string
    {
        return match (true) {
            $definition->tracking_mode !== TrackingMode::QUANTITY => __('storefront.errors.not_quantity'),
            ! $definition->is_active => __('storefront.errors.product_inactive'),
            $definition->selling_price === null => __('storefront.errors.not_priced'),
            $quantity > $onHand => __('storefront.errors.above_on_hand', ['quantity' => $onHand]),
            default => null,
        };
    }

    /**
     * The reason a device cannot be made eligible, or null when it can.
     */
    public static function deviceProblem(ProductDefinition $definition, InventoryUnit $unit): ?string
    {
        return match (true) {
            ! $definition->tracking_mode->tracksIndividualItems() => __('storefront.errors.not_individual'),
            ! $definition->is_active => __('storefront.errors.product_inactive'),
            $unit->status !== UnitStatus::AVAILABLE => __('storefront.errors.unit_unavailable', ['status' => $unit->status->label()]),
            SellingPrice::listPriceMinor($definition, $unit) === null => __('storefront.errors.not_priced'),
            default => null,
        };
    }
}
