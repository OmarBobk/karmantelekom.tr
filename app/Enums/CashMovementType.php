<?php

declare(strict_types=1);

namespace App\Enums;

enum CashMovementType: string
{
    case SALE_CASH = 'sale_cash';
    case CUSTOMER_PAYMENT_CASH = 'customer_payment_cash';
    case EXPENSE_CASH = 'expense_cash';

    public function label(): string
    {
        return __('pos.cashbox.movement_types.'.$this->value);
    }

    public function direction(): CashMovementDirection
    {
        return $this === self::EXPENSE_CASH ? CashMovementDirection::OUT : CashMovementDirection::IN;
    }
}
