<?php

declare(strict_types=1);

namespace App\Actions\Expenses;

use App\Enums\ExpenseCategory;
use App\Exceptions\Purchasing\InvalidAmountException;
use App\Models\Expense;
use App\Models\Sale;
use App\Services\Purchasing\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Validated expense details, shared by card or transfer expenses and cash payouts.
 *
 * The amount is a canonical decimal in TRY. The date is the branch's calendar day
 * (Europe/Istanbul) and cannot be in the future.
 */
final readonly class ExpenseInput
{
    private function __construct(
        public ExpenseCategory $category,
        public string $description,
        public int $amountMinor,
        public string $expenseDate,
        public ?string $reference,
    ) {}

    /**
     * @param  array{category?: mixed, description?: mixed, amount?: mixed, expense_date?: mixed, reference?: mixed}  $input
     *
     * @throws ValidationException
     */
    public static function from(array $input): self
    {
        $validated = Validator::make($input, [
            'category' => ['required', Rule::enum(ExpenseCategory::class)],
            'description' => ['required', 'string', 'max:'.Expense::MAX_DESCRIPTION_LENGTH],
            'amount' => ['required', 'string', 'max:32'],
            'expense_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.Carbon::now(Sale::DISPLAY_TIMEZONE)->toDateString()],
            'reference' => ['nullable', 'string', 'max:'.Expense::MAX_REFERENCE_LENGTH],
        ], [], [
            'category' => __('pos.expenses.fields.category'),
            'description' => __('pos.expenses.fields.description'),
            'amount' => __('pos.expenses.fields.amount'),
            'expense_date' => __('pos.expenses.fields.date'),
            'reference' => __('pos.expenses.fields.reference'),
        ])->validate();

        $description = Str::trim($validated['description']);

        if ($description === '') {
            throw ValidationException::withMessages(['description' => __('pos.expenses.errors.description_required')]);
        }

        try {
            $amountMinor = Money::parse($validated['amount'], Money::MAX_TOTAL_AMOUNT);
        } catch (InvalidAmountException) {
            throw ValidationException::withMessages(['amount' => __('pos.expenses.errors.amount_invalid')]);
        }

        return new self(
            ExpenseCategory::from($validated['category']),
            $description,
            $amountMinor,
            $validated['expense_date'],
            Str::trim((string) ($validated['reference'] ?? '')) ?: null,
        );
    }

    /**
     * @return array{category: string, description: string, amount: int, expense_date: string, reference: string|null}
     */
    public function fingerprintPayload(): array
    {
        return [
            'category' => $this->category->value,
            'description' => $this->description,
            'amount' => $this->amountMinor,
            'expense_date' => $this->expenseDate,
            'reference' => $this->reference,
        ];
    }
}
