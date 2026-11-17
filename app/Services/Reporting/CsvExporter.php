<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * UTF-8 BOM CSV downloads with comma delimiters, exact decimal money, and spreadsheet injection
 * neutralization for user-controlled text. Trusted numeric amount fields are written as plain
 * decimals. CSV has no column types — Excel Import Data should mark identifier columns as Text.
 */
final class CsvExporter
{
    /**
     * @param  list<string>  $headers
     * @param  iterable<int, list<string|int|float|null>>  $rows
     */
    public static function download(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                fputcsv($handle, array_map(self::cell(...), $row));
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Neutralize formula-like leading characters in user-controlled text. Leaves ordinary
     * decimals and plain numbers unchanged when they do not start with a formula trigger.
     */
    public static function text(?string $value): string
    {
        $value = (string) $value;

        if ($value === '') {
            return '';
        }

        $trimmed = ltrim($value, " \t\0\x0B");
        $lead = $trimmed === '' ? '' : $trimmed[0];

        if (in_array($lead, ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }

    /**
     * Exact money decimal for CSV (e.g. 12.50). Not formula-neutralized.
     */
    public static function amount(string $decimal): string
    {
        return $decimal;
    }

    private static function cell(string|int|float|null $value): string
    {
        if ($value === null) {
            return '';
        }

        return (string) $value;
    }
}
