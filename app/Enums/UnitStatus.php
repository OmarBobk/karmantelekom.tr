<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Availability of an individually tracked inventory unit.
 *
 * The full device lifecycle (reserved, sold, repair, …) is intentionally deferred.
 */
enum UnitStatus: string
{
    case AVAILABLE = 'available';
    case REMOVED = 'removed';

    public function label(): string
    {
        return __('inventory.unit_statuses.'.$this->value);
    }
}
