<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Exceptions\Purchasing\InvalidAmountException;

/**
 * The Karman financial format for operator-facing TRY amounts: Latin digits, period groups of
 * three, and a decimal comma ("10.000,50"), regardless of the interface language.
 *
 * Localized text is converted here into the canonical dot-decimal string ("10000.50") that
 * Money::parse, the POS actions, and stored attempt payloads already accept. Money::parse itself
 * keeps its legacy grammar, where a period is a decimal mark.
 */
final class LocalizedMoney
{
    public const EXAMPLE = '10.000,50';

    private const GRAMMAR = '/^(\d+|[1-9]\d{0,2}(?:\.\d{3})+)(?:,(\d*))?$/';

    /**
     * Localized operator text to the canonical decimal string; blank stays blank.
     *
     * Accepts Latin, Arabic-Indic, or Persian digits, optional period grouping in exact groups
     * of three, and a decimal comma with at most two digits. Dot decimals ("12.50") and comma
     * grouping ("1,234.56") are rejected rather than guessed.
     *
     * @throws InvalidAmountException
     */
    public static function toCanonical(string $input): string
    {
        $value = Money::latinDigits(trim($input));
        $value = str_replace(['٫', '٬', ' ', "\u{00A0}", "\u{202F}", "\u{200E}", "\u{200F}", "\u{061C}"], [',', '.', '', '', '', '', '', ''], $value);

        if ($value === '') {
            return '';
        }

        if (preg_match(self::GRAMMAR, $value, $matches) !== 1) {
            throw new InvalidAmountException(InvalidAmountException::FORMAT);
        }

        $fraction = $matches[2] ?? '';

        if (strlen($fraction) > 2) {
            throw new InvalidAmountException(InvalidAmountException::PRECISION);
        }

        $whole = str_replace('.', '', $matches[1]);

        return $fraction === '' ? $whole : $whole.'.'.$fraction;
    }

    /**
     * Localized operator text to exact minor units, with Money::parse's bounds and zero rule.
     *
     * @throws InvalidAmountException
     */
    public static function parse(string $input, int $max = Money::MAX_TOTAL_AMOUNT, bool $allowZero = false): int
    {
        return Money::parse(self::toCanonical($input), $max, $allowZero);
    }

    /**
     * Display form with two decimals, e.g. "10.000,50" or "-3.000,00".
     */
    public static function format(int $minor): string
    {
        $sign = $minor < 0 ? '-' : '';
        $absolute = abs($minor);

        return $sign.number_format(intdiv($absolute, 100), 0, ',', '.').','.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Editable form that omits zero kuruş, e.g. "10.000" or "10.000,50".
     */
    public static function input(int $minor): string
    {
        $formatted = self::format($minor);

        return str_ends_with($formatted, ',00') ? substr($formatted, 0, -3) : $formatted;
    }
}
