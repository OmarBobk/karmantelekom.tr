<?php

declare(strict_types=1);

namespace App\Exceptions\Inventory;

use RuntimeException;

/**
 * A Phase 4 rollback was refused because recorded device lifecycle data would be lost.
 * Thrown before any schema change, so history tables, columns, and constraints stay intact.
 */
class LifecycleRollbackBlockedException extends RuntimeException
{
    /**
     * @param  array<string, int>  $recordedData  Description => row count.
     */
    public function __construct(public readonly array $recordedData)
    {
        $summary = collect($recordedData)
            ->map(fn (int $count, string $description): string => "{$description}: {$count}")
            ->implode('; ');

        parent::__construct("Phase 4 rollback refused: recorded device lifecycle data exists ({$summary}). Nothing was changed. Fix forward with a new migration instead of rolling back.");
    }
}
