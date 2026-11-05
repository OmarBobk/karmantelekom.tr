<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Enums\Currency;
use App\Enums\DeviceCostCategory;
use App\Exceptions\Purchasing\InvalidAmountException;
use App\Services\Purchasing\Money;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A validated device cost: category, optional description, and either a known amount
 * (zero allowed) in one currency or an explicitly unknown amount with no currency.
 */
final readonly class DeviceCostInput
{
    public const MAX_DESCRIPTION_LENGTH = 255;

    private function __construct(
        public DeviceCostCategory $category,
        public ?string $description,
        public ?int $amountMinor,
        public ?Currency $currency,
    ) {}

    /**
     * Parse operator input. Error keys are prefixed, e.g. "parts_amount" for prefix "parts_".
     *
     * @throws ValidationException
     */
    public static function fromInput(
        ?string $category,
        ?string $description,
        ?string $amount,
        ?string $currency,
        bool $unknown,
        string $prefix = 'cost_',
    ): self {
        $errors = [];
        $category = DeviceCostCategory::tryFrom((string) $category);
        $description = Str::trim((string) $description);
        $description = $description === '' ? null : $description;

        if ($category === null) {
            $errors[$prefix.'category'] = __('devices.errors.cost_category');
        } elseif ($category->requiresDescription() && $description === null) {
            $errors[$prefix.'description'] = __('devices.errors.required', ['attribute' => __('devices.fields.cost_description')]);
        }

        if ($description !== null && mb_strlen($description) > self::MAX_DESCRIPTION_LENGTH) {
            $errors[$prefix.'description'] = __('operations.validation.max', ['attribute' => __('devices.fields.cost_description'), 'max' => self::MAX_DESCRIPTION_LENGTH]);
        }

        $amountMinor = null;
        $parsedCurrency = null;

        if (! $unknown) {
            $parsedCurrency = Currency::tryFrom((string) $currency);

            if ($parsedCurrency === null) {
                $errors[$prefix.'currency'] = __('devices.errors.cost_currency');
            }

            if (Str::trim((string) $amount) === '') {
                $errors[$prefix.'amount'] = __('devices.errors.cost_amount_required');
            } else {
                try {
                    $amountMinor = Money::parse((string) $amount, Money::MAX_UNIT_AMOUNT, allowZero: true);
                } catch (InvalidAmountException) {
                    $errors[$prefix.'amount'] = __('devices.errors.cost_amount_invalid', ['max' => Money::format(Money::MAX_UNIT_AMOUNT)]);
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return new self($category, $description, $amountMinor, $parsedCurrency);
    }

    public function withCategory(DeviceCostCategory $category): self
    {
        return new self($category, $this->description, $this->amountMinor, $this->currency);
    }

    public function isUnknown(): bool
    {
        return $this->amountMinor === null;
    }

    /**
     * Normalized values for idempotency fingerprints.
     *
     * @return array{category: string, description: ?string, amount_minor: ?int, currency: ?string}
     */
    public function fingerprint(): array
    {
        return [
            'category' => $this->category->value,
            'description' => $this->description,
            'amount_minor' => $this->amountMinor,
            'currency' => $this->currency?->value,
        ];
    }

    /**
     * @return array{category: DeviceCostCategory, description: ?string, amount: ?string, currency: ?Currency}
     */
    public function attributes(): array
    {
        return [
            'category' => $this->category,
            'description' => $this->description,
            'amount' => $this->amountMinor === null ? null : Money::toDecimal($this->amountMinor),
            'currency' => $this->currency,
        ];
    }
}
