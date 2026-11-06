<?php

declare(strict_types=1);

namespace App\Actions\Pos;

use App\Enums\TrackingMode;
use App\Exceptions\Purchasing\InvalidAmountException;
use App\Models\InventoryUnit;
use App\Models\ProductDefinition;
use App\Services\Purchasing\Money;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Selling price rules shared by pricing screens and checkout.
 *
 * Quantity and serialized items use their definition's shared price. Used devices use only
 * their own unit price, never the definition's. NULL means not priced and not sellable.
 */
final class SellingPrice
{
    /**
     * Parse an operator price into minor units; blank means "not priced".
     *
     * @throws ValidationException
     */
    public static function parse(?string $input, string $field): ?int
    {
        if (Str::trim((string) $input) === '') {
            return null;
        }

        try {
            return Money::parse((string) $input, Money::MAX_UNIT_AMOUNT);
        } catch (InvalidAmountException) {
            throw ValidationException::withMessages([$field => __('pos.errors.price_invalid', ['max' => Money::format(Money::MAX_UNIT_AMOUNT)])]);
        }
    }

    /**
     * The current list price of one item, or null when it is not priced.
     */
    public static function listPriceMinor(ProductDefinition $definition, ?InventoryUnit $unit): ?int
    {
        if ($definition->tracking_mode === TrackingMode::USED_DEVICE) {
            return $unit?->sellingPriceMinor();
        }

        return $definition->sellingPriceMinor();
    }

    public static function usesUnitPrice(TrackingMode $mode): bool
    {
        return $mode === TrackingMode::USED_DEVICE;
    }
}
