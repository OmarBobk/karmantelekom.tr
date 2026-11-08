<?php

declare(strict_types=1);

namespace App\Enums;

enum CashIntakeSource: string
{
    case SALE = 'sale';
    case CUSTOMER_PAYMENT = 'customer_payment';
}
