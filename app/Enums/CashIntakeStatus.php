<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Pending from the moment staff are told to accept the cash until the sale or customer payment
 * posts it (completed) or the owner records that completion is impossible (released).
 */
enum CashIntakeStatus: string
{
    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case RELEASED = 'released';
}
