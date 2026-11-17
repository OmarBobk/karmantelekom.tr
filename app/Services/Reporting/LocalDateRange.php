<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Models\Sale;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Inclusive ranges of Istanbul calendar dates, the branches' operating days. Business-date
 * columns (expense_date) compare the dates directly; timestamps compare against the UTC
 * instants from the start of the first day up to, but excluding, the start of the day after
 * the last.
 */
final class LocalDateRange
{
    public const GRANULARITY_HOUR = 'hour';

    public const GRANULARITY_DAY = 'day';

    public const GRANULARITY_WEEK = 'week';

    public const GRANULARITY_MONTH = 'month';

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

    public static function yesterday(): string
    {
        return CarbonImmutable::now(Sale::DISPLAY_TIMEZONE)->subDay()->toDateString();
    }

    /**
     * Inclusive Istanbul dates for the last seven calendar days ending today.
     *
     * @return array{0: string, 1: string}
     */
    public static function lastSevenDays(): array
    {
        $today = CarbonImmutable::now(Sale::DISPLAY_TIMEZONE)->startOfDay();

        return [$today->subDays(6)->toDateString(), $today->toDateString()];
    }

    /**
     * Inclusive Istanbul dates from the first day of this month through today.
     *
     * @return array{0: string, 1: string}
     */
    public static function thisMonth(): array
    {
        $today = CarbonImmutable::now(Sale::DISPLAY_TIMEZONE)->startOfDay();

        return [$today->startOfMonth()->toDateString(), $today->toDateString()];
    }

    /**
     * The inclusive Istanbul range of the same length that ends the day before $from.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function previousEqual(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $days = (int) $from->diffInDays($to) + 1;
        $previousTo = $from->subDay();
        $previousFrom = $previousTo->subDays($days - 1);

        return [$previousFrom, $previousTo];
    }

    /**
     * Whether the inclusive Istanbul range includes today's operating day.
     */
    public static function includesToday(CarbonImmutable $from, CarbonImmutable $to): bool
    {
        $today = CarbonImmutable::now(Sale::DISPLAY_TIMEZONE)->startOfDay();

        return ! $today->lessThan($from) && ! $today->greaterThan($to);
    }

    /**
     * Readable time-bucket size for a chart over the selected range. The range itself is never clamped.
     */
    public static function chartGranularity(CarbonImmutable $from, CarbonImmutable $to): string
    {
        $days = (int) $from->diffInDays($to) + 1;

        return match (true) {
            $days === 1 => self::GRANULARITY_HOUR,
            $days <= 45 => self::GRANULARITY_DAY,
            $days <= 180 => self::GRANULARITY_WEEK,
            default => self::GRANULARITY_MONTH,
        };
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

    /**
     * Contiguous Istanbul buckets covering $from through $to at the given granularity.
     *
     * @return list<array{key: string, label: string, start: CarbonImmutable, end: CarbonImmutable}>
     */
    public static function buckets(CarbonImmutable $from, CarbonImmutable $to, string $granularity): array
    {
        $from = $from->setTimezone(Sale::DISPLAY_TIMEZONE)->startOfDay();
        $to = $to->setTimezone(Sale::DISPLAY_TIMEZONE)->startOfDay();
        $rangeEnd = $to->addDay();
        $buckets = [];

        if ($granularity === self::GRANULARITY_HOUR) {
            for ($cursor = $from; $cursor->lessThan($rangeEnd); $cursor = $cursor->addHour()) {
                $next = $cursor->addHour();
                $buckets[] = [
                    'key' => $cursor->format('Y-m-d H:00'),
                    'label' => $cursor->format('H:00'),
                    'start' => $cursor->utc(),
                    'end' => $next->utc(),
                ];
            }

            return $buckets;
        }

        if ($granularity === self::GRANULARITY_DAY) {
            for ($cursor = $from; $cursor->lessThan($rangeEnd); $cursor = $cursor->addDay()) {
                $next = $cursor->addDay();
                $buckets[] = [
                    'key' => $cursor->toDateString(),
                    'label' => $cursor->format('d M'),
                    'start' => $cursor->utc(),
                    'end' => $next->utc(),
                ];
            }

            return $buckets;
        }

        if ($granularity === self::GRANULARITY_WEEK) {
            for ($cursor = $from; $cursor->lessThan($rangeEnd); $cursor = $cursor->addDays(7)) {
                $next = $cursor->addDays(7);
                if ($next->greaterThan($rangeEnd)) {
                    $next = $rangeEnd;
                }
                $buckets[] = [
                    'key' => $cursor->toDateString(),
                    'label' => $cursor->format('d M').'–'.$next->subDay()->format('d M'),
                    'start' => $cursor->utc(),
                    'end' => $next->utc(),
                ];
            }

            return $buckets;
        }

        if ($granularity === self::GRANULARITY_MONTH) {
            $cursor = $from->startOfMonth();
            while ($cursor->lessThan($rangeEnd)) {
                $monthStart = $cursor->lessThan($from) ? $from : $cursor;
                $nextMonth = $cursor->addMonthNoOverflow()->startOfMonth();
                $next = $nextMonth->greaterThan($rangeEnd) ? $rangeEnd : $nextMonth;
                if ($monthStart->lessThan($next)) {
                    $buckets[] = [
                        'key' => $cursor->format('Y-m'),
                        'label' => $cursor->format('M Y'),
                        'start' => $monthStart->utc(),
                        'end' => $next->utc(),
                    ];
                }
                $cursor = $nextMonth;
            }

            return $buckets;
        }

        throw new InvalidArgumentException("Unknown chart granularity [{$granularity}].");
    }
}
