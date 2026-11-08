<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Whether a movement put cash into the drawer or took it out. Amounts are always positive; the
 * expected closing cash adds movements in and subtracts movements out.
 */
enum CashMovementDirection: string
{
    case IN = 'in';
    case OUT = 'out';
}
