<?php

declare(strict_types=1);

namespace App\Enums;

enum CashMovementType: string
{
    case SALE_CASH = 'sale_cash';
    case CUSTOMER_PAYMENT_CASH = 'customer_payment_cash';
    case EXPENSE_CASH = 'expense_cash';
    case SUPPLIER_PAYMENT_CASH = 'supplier_payment_cash';
    case CASH_DEPOSIT = 'cash_deposit';
    case CASH_WITHDRAWAL = 'cash_withdrawal';

    public function label(): string
    {
        return __('pos.cashbox.movement_types.'.$this->value);
    }

    public function direction(): CashMovementDirection
    {
        return match ($this) {
            self::EXPENSE_CASH, self::SUPPLIER_PAYMENT_CASH, self::CASH_WITHDRAWAL => CashMovementDirection::OUT,
            default => CashMovementDirection::IN,
        };
    }

    /**
     * Whether this movement only moves the owner's cash into or out of the drawer: it changes
     * the expected cash but is neither revenue nor expense.
     */
    public function isInternalTransfer(): bool
    {
        return $this === self::CASH_DEPOSIT || $this === self::CASH_WITHDRAWAL;
    }
}
