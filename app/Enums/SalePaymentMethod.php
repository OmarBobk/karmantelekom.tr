<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tender recorded on a completed sale. Card payments are approved on an external terminal;
 * the application never processes cards or stores card numbers.
 */
enum SalePaymentMethod: string
{
    case CASH = 'cash';
    case CARD = 'card';

    public function label(): string
    {
        return __('pos.payment_methods.'.$this->value);
    }
}
