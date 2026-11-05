<?php

declare(strict_types=1);

namespace App\Exceptions\Inventory;

use RuntimeException;

/**
 * An idempotency key was reused by a different actor or for a different request.
 */
class IdempotencyConflictException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The idempotency key was already used for a different inventory request.');
    }
}
