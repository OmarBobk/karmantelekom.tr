<?php

declare(strict_types=1);

namespace App\Exceptions\Pos;

use RuntimeException;

/**
 * A Phase 5 rollback was refused because recorded POS data would be lost.
 * Thrown before any schema change.
 */
class PosRollbackBlockedException extends RuntimeException
{
    /**
     * @param  array<string, int>  $recordedData  Description => row count.
     */
    public function __construct(public readonly array $recordedData)
    {
        $summary = collect($recordedData)
            ->map(fn (int $count, string $description): string => "{$description}: {$count}")
            ->implode('; ');

        parent::__construct("Phase 5 rollback refused: recorded POS data exists ({$summary}). Nothing was changed. Fix forward with a new migration instead of rolling back.");
    }
}
