<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Exceptions\Purchasing\InvalidAmountException;

/**
 * Exact money arithmetic on integer minor units (cents/kuruş). Floats are never used.
 *
 * Every amount is bounded so that sums of up to MAX_LINES line totals stay far inside
 * PHP's 64-bit integers and the DECIMAL(15,2) columns that store them.
 */
final class Money
{
    /** 99,999,999.99 */
    public const MAX_UNIT_AMOUNT = 9_999_999_999;

    /** 9,999,999,999.99 — the ceiling for line totals, charges, invoice totals, and payments. */
    public const MAX_TOTAL_AMOUNT = 999_999_999_999;

    public const MAX_LINES = 200;

    private const ARABIC_DIGITS = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩', '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    private const LATIN_DIGITS = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    /**
     * Parse operator input into minor units.
     *
     * Accepts Latin or Arabic-Indic digits, "." or "٫" as the decimal mark, and
     * comma groups of three ("8,000.00"). A single comma followed by one or two
     * digits is a decimal comma ("12,50"). Anything else is rejected, never guessed.
     *
     * @throws InvalidAmountException
     */
    public static function parse(string $input, int $max = self::MAX_TOTAL_AMOUNT, bool $allowZero = false): int
    {
        $value = str_replace(self::ARABIC_DIGITS, self::LATIN_DIGITS, trim($input));
        $value = str_replace(['٫', '٬', ' ', "\u{00A0}", "\u{202F}"], ['.', ',', '', '', ''], $value);

        if (preg_match('/^\d+,\d{1,2}$/', $value) === 1) {
            $value = str_replace(',', '.', $value);
        } elseif (str_contains($value, ',')) {
            if (preg_match('/^\d{1,3}(,\d{3})+(\.\d*)?$/', $value) !== 1) {
                throw new InvalidAmountException(InvalidAmountException::FORMAT);
            }

            $value = str_replace(',', '', $value);
        }

        if (preg_match('/^(\d+)(?:\.(\d*))?$/', $value, $matches) !== 1) {
            throw new InvalidAmountException(InvalidAmountException::FORMAT);
        }

        $whole = ltrim($matches[1], '0');
        $fraction = rtrim($matches[2] ?? '', '0');

        if (strlen($fraction) > 2) {
            throw new InvalidAmountException(InvalidAmountException::PRECISION);
        }

        if (strlen($whole) > 13) {
            throw new InvalidAmountException(InvalidAmountException::TOO_LARGE);
        }

        $minor = (int) $whole * 100 + (int) str_pad($fraction, 2, '0');

        if ($minor > $max) {
            throw new InvalidAmountException(InvalidAmountException::TOO_LARGE);
        }

        if ($minor === 0 && ! $allowZero) {
            throw new InvalidAmountException(InvalidAmountException::NOT_POSITIVE);
        }

        return $minor;
    }

    /**
     * Quantity × unit amount, rejected when the product leaves the line-total bound.
     *
     * @throws InvalidAmountException
     */
    public static function multiply(int $quantity, int $unitMinor): int
    {
        if ($quantity < 0 || $unitMinor < 0) {
            throw new InvalidAmountException(InvalidAmountException::FORMAT);
        }

        if ($unitMinor !== 0 && $quantity > intdiv(self::MAX_TOTAL_AMOUNT, $unitMinor)) {
            throw new InvalidAmountException(InvalidAmountException::TOO_LARGE);
        }

        return $quantity * $unitMinor;
    }

    /**
     * @param  iterable<int>  $amounts
     *
     * @throws InvalidAmountException
     */
    public static function sum(iterable $amounts): int
    {
        $total = 0;

        foreach ($amounts as $amount) {
            $total += $amount;

            if ($total > self::MAX_TOTAL_AMOUNT) {
                throw new InvalidAmountException(InvalidAmountException::TOO_LARGE);
            }
        }

        return $total;
    }

    /**
     * Read a DECIMAL column value ("8175.00", "-50.00") into minor units.
     */
    public static function fromDecimal(string|int|null $stored): int
    {
        if ($stored === null) {
            return 0;
        }

        if (is_int($stored)) {
            return $stored * 100;
        }

        if (preg_match('/^(-?)(\d+)(?:\.(\d{1,2}))?$/', trim($stored), $matches) !== 1) {
            throw new InvalidAmountException(InvalidAmountException::FORMAT);
        }

        $minor = (int) $matches[2] * 100 + (int) str_pad($matches[3] ?? '', 2, '0');

        return $matches[1] === '-' ? -$minor : $minor;
    }

    /**
     * Minor units to the exact DECIMAL string written to the database.
     */
    public static function toDecimal(int $minor): string
    {
        $sign = $minor < 0 ? '-' : '';
        $absolute = abs($minor);

        return $sign.intdiv($absolute, 100).'.'.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Display form with thousands separators, e.g. "8,175.00" or "-3,000.00".
     */
    public static function format(int $minor): string
    {
        $sign = $minor < 0 ? '-' : '';
        $absolute = abs($minor);

        return $sign.number_format(intdiv($absolute, 100)).'.'.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Plain editable form without separators, e.g. "12.50".
     */
    public static function input(int $minor): string
    {
        return self::toDecimal($minor);
    }
}
