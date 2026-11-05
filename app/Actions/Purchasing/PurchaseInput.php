<?php

declare(strict_types=1);

namespace App\Actions\Purchasing;

use App\Exceptions\Purchasing\InvalidAmountException;
use App\Services\Purchasing\Money;
use Illuminate\Validation\ValidationException;

/**
 * Converts operator money input to minor units, collecting translated errors keyed by field.
 */
final class PurchaseInput
{
    /** @var array<string, string> */
    private array $errors = [];

    /**
     * @return int|null Minor units, or null when the value was invalid (an error is recorded).
     */
    public function amount(?string $value, string $field, string $attribute, int $max = Money::MAX_TOTAL_AMOUNT, bool $allowZero = false): ?int
    {
        if ($value === null || trim($value) === '') {
            if ($allowZero) {
                return 0;
            }

            $this->errors[$field] = __('operations.validation.required', ['attribute' => $attribute]);

            return null;
        }

        try {
            return Money::parse($value, $max, $allowZero);
        } catch (InvalidAmountException $exception) {
            $this->errors[$field] = self::message($exception, $attribute, $max);

            return null;
        }
    }

    public function add(string $field, string $message): void
    {
        $this->errors[$field] ??= $message;
    }

    public function hasError(string $field): bool
    {
        return isset($this->errors[$field]);
    }

    /**
     * @throws ValidationException
     */
    public function throwIfInvalid(): void
    {
        if ($this->errors !== []) {
            throw ValidationException::withMessages($this->errors);
        }
    }

    public static function message(InvalidAmountException $exception, string $attribute, int $max = Money::MAX_TOTAL_AMOUNT): string
    {
        return match ($exception->reason) {
            InvalidAmountException::PRECISION => __('purchases.errors.amount_precision', ['attribute' => $attribute]),
            InvalidAmountException::TOO_LARGE => __('purchases.errors.amount_too_large', ['attribute' => $attribute, 'max' => Money::format($max)]),
            InvalidAmountException::NOT_POSITIVE => __('purchases.errors.amount_positive', ['attribute' => $attribute]),
            default => __('purchases.errors.amount_format', ['attribute' => $attribute]),
        };
    }
}
