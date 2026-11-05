<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Reasons recorded on inventory movements.
 */
enum AdjustmentReason: string
{
    case OPENING_BALANCE = 'opening_balance';
    case COUNT_CORRECTION = 'count_correction';
    case FOUND = 'found';
    case DAMAGED = 'damaged';
    case LOST = 'lost';
    case PURCHASE = 'purchase';
    case INSPECTION = 'inspection';
    case REPAIR = 'repair';
    case DEVICE_COST = 'device_cost';
    case IDENTITY_CORRECTION = 'identity_correction';

    public function label(): string
    {
        return __('inventory.reasons.'.$this->value);
    }
}
