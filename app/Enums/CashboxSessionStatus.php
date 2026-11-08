<?php

declare(strict_types=1);

namespace App\Enums;

enum CashboxSessionStatus: string
{
    case OPEN = 'open';
    case CLOSED = 'closed';
}
