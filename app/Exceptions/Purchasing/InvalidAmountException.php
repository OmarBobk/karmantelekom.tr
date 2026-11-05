<?php

declare(strict_types=1);

namespace App\Exceptions\Purchasing;

use InvalidArgumentException;

/**
 * A money input that cannot be represented exactly within the allowed bounds.
 */
class InvalidAmountException extends InvalidArgumentException
{
    public const FORMAT = 'format';

    public const PRECISION = 'precision';

    public const TOO_LARGE = 'too_large';

    public const NOT_POSITIVE = 'not_positive';

    public function __construct(public readonly string $reason)
    {
        parent::__construct("Invalid money amount ({$reason}).");
    }
}
