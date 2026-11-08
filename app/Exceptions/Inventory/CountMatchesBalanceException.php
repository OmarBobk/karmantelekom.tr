<?php

declare(strict_types=1);

namespace App\Exceptions\Inventory;

use RuntimeException;

/**
 * A physical count equal to the verified balance changes no stock, so no movement is written.
 */
class CountMatchesBalanceException extends RuntimeException
{
    public function __construct(public readonly int $balance)
    {
        parent::__construct('The counted total matches the current balance.');
    }
}
