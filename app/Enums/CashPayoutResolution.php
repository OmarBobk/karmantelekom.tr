<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What the owner verified before releasing a pending cash payout.
 */
enum CashPayoutResolution: string
{
    case NOT_HANDED_OUT = 'not_handed_out';
    case RETURNED = 'returned';

    public function label(): string
    {
        return __('pos.expenses.resolutions.'.$this->value);
    }
}
