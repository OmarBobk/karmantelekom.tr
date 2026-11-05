<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a supplier payment was made. Recorded for reference only; no cashbox entries yet.
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
