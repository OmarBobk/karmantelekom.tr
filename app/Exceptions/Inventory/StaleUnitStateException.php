<?php

declare(strict_types=1);

namespace App\Exceptions\Inventory;

use App\Enums\UnitStatus;
use RuntimeException;

/**
 * The unit changed after the form was opened (another operator acted on it), so the
 * submitted action was not applied. Nothing was written.
 */
class StaleUnitStateException extends RuntimeException
{
    public function __construct(
        public readonly UnitStatus $currentStatus,
        public readonly int $currentRevision,
    ) {
        parent::__construct('The unit changed since the form was opened.');
    }
}
