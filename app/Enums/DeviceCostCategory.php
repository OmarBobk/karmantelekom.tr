<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Attributable device costs, recorded separately from the invoice acquisition price.
 *
 * Recording a cost does not make it part of a recognized cost basis; that rule is not approved yet.
 */
enum DeviceCostCategory: string
{
    case REPAIR_PARTS = 'repair_parts';
    case REPAIR_LABOR = 'repair_labor';
    case ADDITIONAL_ACQUISITION = 'additional_acquisition';
    case OTHER = 'other';

    public function requiresDescription(): bool
    {
        return $this === self::REPAIR_PARTS || $this === self::OTHER;
    }

    public function label(): string
    {
        return __('devices.cost_categories.'.$this->value);
    }
}
