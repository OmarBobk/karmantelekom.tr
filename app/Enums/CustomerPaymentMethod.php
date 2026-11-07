<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a customer paid toward their debts. Cash gets a receipt number; card and bank transfer
 * carry the external reference. No card numbers are stored.
 */
enum CustomerPaymentMethod: string
{
    case CASH = 'cash';
    case CARD = 'card';
    case BANK_TRANSFER = 'bank_transfer';

    public function label(): string
    {
        return __('pos.customer_accounts.methods.'.$this->value);
    }

    public function needsReference(): bool
    {
        return $this !== self::CASH;
    }
}
