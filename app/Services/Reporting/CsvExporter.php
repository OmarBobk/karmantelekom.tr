<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * UTF-8 BOM CSV downloads with comma delimiters, RFC-style quote doubling, exact decimal money,
 * and spreadsheet injection neutralization for user-controlled text. Trusted monetary decimals are
 * written as plain numbers. CSV has no column types — Excel Import Data should mark identifier
 * columns as Text. Neutralization reduces formula risk on open; it is not a guarantee after a
 * spreadsheet edits, saves, or reopens the file.
 */
final class CsvExporter
{
    public const DELIMITER = ',';

    public const ENCLOSURE = '"';

    /** Empty escape: standard CSV doubles enclosures; no proprietary backslash escaping. */
    public const ESCAPE = '';

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
            self::writeRow($handle, $headers);

            foreach ($rows as $row) {
                self::writeRow($handle, array_map(self::cell(...), $row));
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Neutralize formula-like values. Leading whitespace and C0 controls before =, +, -, or @
     * still trigger protection; the original characters are kept after a leading apostrophe.
     */
    public static function text(?string $value): string
    {
        $value = (string) $value;

        if ($value === '') {
            return '';
        }

        $length = strlen($value);
        $offset = 0;

        while ($offset < $length && self::isIgnorablePrefix($value[$offset])) {
            $offset++;
        }

        if ($offset < $length && in_array($value[$offset], ['=', '+', '-', '@'], true)) {
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

    /**
     * @param  resource  $handle
     * @param  list<string|int|float|null>  $fields
     */
    public static function writeRow($handle, array $fields): void
    {
        fputcsv($handle, $fields, self::DELIMITER, self::ENCLOSURE, self::ESCAPE);
    }

    private static function isIgnorablePrefix(string $byte): bool
    {
        $code = ord($byte);

        return $code <= 0x20;
    }

    private static function cell(string|int|float|null $value): string
    {
        if ($value === null) {
            return '';
        }

        return (string) $value;
    }
}
