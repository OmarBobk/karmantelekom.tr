<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * An owner moving cash into or out of the drawer, outside any sale, payment, or expense.
 */
enum CashboxTransferKind: string
{
    case DEPOSIT = 'deposit';
    case WITHDRAWAL = 'withdrawal';

    public function label(): string
    {
        return __('pos.cashbox.transfers.kinds.'.$this->value);
    }

    public function movementType(): CashMovementType
    {
        return $this === self::DEPOSIT ? CashMovementType::CASH_DEPOSIT : CashMovementType::CASH_WITHDRAWAL;
    }
}
