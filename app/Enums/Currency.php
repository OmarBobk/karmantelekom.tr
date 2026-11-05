<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Currencies a supplier invoice may be issued in. Balances are never converted between them.
 */
enum Currency: string
{
    case TRY = 'TRY';
    case USD = 'USD';

    /**
     * Digits after the decimal point; amounts are stored and computed as integers of this precision.
     */
    public function minorUnitDigits(): int
    {
        return 2;
    }

    public function label(): string
    {
        return __('purchases.currencies.'.$this->value);
    }
}
