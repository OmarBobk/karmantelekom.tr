<?php

declare(strict_types=1);

namespace App\Services\Reporting;

/**
 * Chunk size for export queries that eager-load relations. Overridable in tests to prove
 * related queries grow per batch rather than per row.
 */
final class ReportCsvBatch
{
    private static int $size = 200;

    public static function size(): int
    {
        return self::$size;
    }

    public static function using(int $size, callable $callback): mixed
    {
        $previous = self::$size;
        self::$size = max(1, $size);

        try {
            return $callback();
        } finally {
            self::$size = $previous;
        }
    }
}
