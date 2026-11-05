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

    public function label(): string
    {
        return __('inventory.reasons.'.$this->value);
    }
}
