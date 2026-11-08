<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a supplier payment was made. Only TRY cash from a branch whose cash tracking started moves
 * cashbox cash, through a supplier cash payout; every other payment is recorded for reference.
 */
enum SupplierPaymentMethod: string
{
    case CASH = 'cash';
    case BANK_TRANSFER = 'bank_transfer';
    case CARD = 'card';
    case OTHER = 'other';

    public function label(): string
    {
        return __('purchases.payment_methods.'.$this->value);
    }
}
