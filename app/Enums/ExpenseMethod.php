<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How an expense was paid. Only cash leaves the cashbox; card and bank transfer expenses appear
 * in expense history alone.
 */
enum ExpenseMethod: string
{
    case CASH = 'cash';
    case CARD = 'card';
    case BANK_TRANSFER = 'bank_transfer';

    public function label(): string
    {
        return __('pos.expenses.methods.'.$this->value);
    }
}
