<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lifecycle status of an individually tracked inventory unit.
 *
 * Only AVAILABLE counts as available stock. INSPECTION, IN_REPAIR, and REVIEW are still owned
 * by the store but unavailable; REVIEW holds a device found without a verified source until an
 * owner approves or removes it. A removed device found again returns through REVIEW; otherwise
 * REMOVED and SOLD are final until an approved return workflow exists. Values must fit the
 * 16-character columns.
 */
enum UnitStatus: string
{
    case AVAILABLE = 'available';
    case INSPECTION = 'inspection';
    case IN_REPAIR = 'in_repair';
    case REVIEW = 'review';
    case REMOVED = 'removed';
    case SOLD = 'sold';

    public function label(): string
    {
        return __('inventory.unit_statuses.'.$this->value);
    }

    public function isInService(): bool
    {
        return $this === self::INSPECTION || $this === self::IN_REPAIR;
    }

    public function allowsRemoval(): bool
    {
        return $this === self::AVAILABLE || $this === self::INSPECTION || $this === self::REVIEW;
    }

    /**
     * Badge classes shared by unit lists and the unit page.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::AVAILABLE => 'bg-green-50 text-green-800 ring-green-600/20',
            self::INSPECTION => 'bg-amber-50 text-amber-800 ring-amber-600/30',
            self::IN_REPAIR => 'bg-sky-50 text-sky-800 ring-sky-600/30',
            self::REVIEW => 'bg-orange-50 text-orange-800 ring-orange-600/30',
            self::REMOVED => 'bg-gray-100 text-gray-700 ring-gray-500/20',
            self::SOLD => 'bg-violet-50 text-violet-800 ring-violet-600/20',
        };
    }
}
