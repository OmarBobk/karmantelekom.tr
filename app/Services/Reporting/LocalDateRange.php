<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Models\Sale;
use Carbon\CarbonImmutable;

/**
 * Inclusive ranges of Istanbul calendar dates, the branches' operating days. Business-date
 * columns (expense_date) compare the dates directly; timestamps compare against the UTC
 * instants from the start of the first day up to, but excluding, the start of the day after
 * the last.
 */
final class LocalDateRange
{
    /**
     * A real Istanbul calendar date in Y-m-d form, or null ("2026-02-30" and "2026-2-3" are refused).
     */
    public static function date(string $value): ?CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, Sale::DISPLAY_TIMEZONE);

        return $date !== null && $date->format('Y-m-d') === $value ? $date : null;
    }

    /**
     * Why a range is unusable ('invalid_date' or 'reversed'), or null when it is valid.
     */
    public static function error(string $from, string $to): ?string
    {
        $start = self::date($from);
        $end = self::date($to);

        if ($start === null || $end === null) {
            return 'invalid_date';
        }

        return $start->greaterThan($end) ? 'reversed' : null;
    }

    public static function today(): string
    {
        return CarbonImmutable::now(Sale::DISPLAY_TIMEZONE)->toDateString();
    }

    /**
     * UTC start (inclusive) and end (exclusive) of the Istanbul days $from through $to.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function utcBounds(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return [
            $from->setTimezone(Sale::DISPLAY_TIMEZONE)->startOfDay()->utc(),
            $to->setTimezone(Sale::DISPLAY_TIMEZONE)->startOfDay()->addDay()->utc(),
        ];
    }
}
