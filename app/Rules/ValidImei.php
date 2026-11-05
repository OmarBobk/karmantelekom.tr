<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * An IMEI is exactly 15 digits whose final digit is a valid Luhn check digit.
 */
class ValidImei implements ValidationRule
{
    public static function isValid(string $value): bool
    {
        if (preg_match('/^\d{15}$/', $value) !== 1) {
            return false;
        }

        $sum = 0;

        foreach (array_reverse(str_split($value)) as $index => $digit) {
            $digit = (int) $digit;

            if ($index % 2 === 1) {
                $digit *= 2;

                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
        }

        return $sum % 10 === 0;
    }

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::isValid($value)) {
            $fail(__('inventory.errors.invalid_imei'));
        }
    }
}
