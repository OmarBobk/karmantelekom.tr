<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How stock of a product definition is tracked in store inventory.
 */
enum TrackingMode: string
{
    /** Counted by quantity (cables, chargers, accessories). */
    case QUANTITY = 'quantity';

    /** New items tracked individually by serial number or IMEI. */
    case SERIALIZED = 'serialized';

    /** Used devices, each with its own identity, condition, and lifecycle. */
    case USED_DEVICE = 'used_device';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function tracksIndividualItems(): bool
    {
        return $this !== self::QUANTITY;
    }

    public function label(): string
    {
        return __('catalog.tracking_modes.'.$this->value);
    }
}
