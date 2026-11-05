<?php

declare(strict_types=1);

namespace App\Exceptions\Purchasing;

use RuntimeException;

/**
 * A receipt was submitted with a new key for an invoice whose goods were already received.
 */
class PurchaseAlreadyReceivedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This purchase has already been received.');
    }
}
