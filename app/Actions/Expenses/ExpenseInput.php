<?php

declare(strict_types=1);

namespace App\Actions\Expenses;

use App\Enums\ExpenseCategory;
use App\Exceptions\Purchasing\InvalidAmountException;
use App\Models\Expense;
use App\Models\InventoryUnit;
use App\Models\OperationalSupplier;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use App\Services\Purchasing\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
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
        public ?int $supplierId,
        public ?int $unitId,
    ) {}

    /**
     * The supplier and device references are optional and for traceability only. A supplier must
     * be active and the actor must be allowed to see suppliers; a device must belong to this
     * branch and the actor must be allowed to see its inventory.
     *
     * @param  array{category?: mixed, description?: mixed, amount?: mixed, expense_date?: mixed, reference?: mixed, operational_supplier_id?: mixed, inventory_unit_id?: mixed}  $input
     *
     * @throws ValidationException
     */
    public static function from(array $input, User $actor, Store $store): self
    {
        $validated = Validator::make($input, [
            'category' => ['required', Rule::enum(ExpenseCategory::class)],
            'description' => ['required', 'string', 'max:'.Expense::MAX_DESCRIPTION_LENGTH],
            'amount' => ['required', 'string', 'max:32'],
            'expense_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.Carbon::now(Sale::DISPLAY_TIMEZONE)->toDateString()],
            'reference' => ['nullable', 'string', 'max:'.Expense::MAX_REFERENCE_LENGTH],
            'operational_supplier_id' => ['nullable', 'integer'],
            'inventory_unit_id' => ['nullable', 'integer'],
        ], [], [
            'category' => __('pos.expenses.fields.category'),
            'description' => __('pos.expenses.fields.description'),
            'amount' => __('pos.expenses.fields.amount'),
            'expense_date' => __('pos.expenses.fields.date'),
            'reference' => __('pos.expenses.fields.reference'),
            'operational_supplier_id' => __('pos.expenses.fields.supplier'),
            'inventory_unit_id' => __('pos.expenses.fields.device'),
        ])->validate();

        $supplierId = blank($validated['operational_supplier_id'] ?? null) ? null : (int) $validated['operational_supplier_id'];
        $unitId = blank($validated['inventory_unit_id'] ?? null) ? null : (int) $validated['inventory_unit_id'];

        if ($supplierId !== null && (Gate::forUser($actor)->denies('viewAny', OperationalSupplier::class)
            || ! OperationalSupplier::query()->whereKey($supplierId)->where('is_active', true)->exists())) {
            throw ValidationException::withMessages(['operational_supplier_id' => __('pos.expenses.errors.supplier_invalid')]);
        }

        if ($unitId !== null && (Gate::forUser($actor)->denies('viewInventory', $store)
            || ! InventoryUnit::query()->whereKey($unitId)->where('store_id', $store->id)->exists())) {
            throw ValidationException::withMessages(['inventory_unit_id' => __('pos.expenses.errors.device_invalid')]);
        }

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
            $supplierId,
            $unitId,
        );
    }

    /**
     * The references are included only when set, so a submission without them keeps the
     * fingerprint it had before references existed and still replays.
     *
     * @return array{category: string, description: string, amount: int, expense_date: string, reference: string|null, operational_supplier_id?: int, inventory_unit_id?: int}
     */
    public function fingerprintPayload(): array
    {
        return [
            'category' => $this->category->value,
            'description' => $this->description,
            'amount' => $this->amountMinor,
            'expense_date' => $this->expenseDate,
            'reference' => $this->reference,
            ...array_filter([
                'operational_supplier_id' => $this->supplierId,
                'inventory_unit_id' => $this->unitId,
            ], fn (?int $id): bool => $id !== null),
        ];
    }
}
