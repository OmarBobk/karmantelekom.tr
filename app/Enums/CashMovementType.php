<?php

declare(strict_types=1);

namespace App\Enums;

enum CashMovementType: string
{
    case SALE_CASH = 'sale_cash';
    case CUSTOMER_PAYMENT_CASH = 'customer_payment_cash';

    public function label(): string
    {
        return __('pos.cashbox.movement_types.'.$this->value);
    }
}
