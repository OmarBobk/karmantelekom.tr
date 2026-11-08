<?php

declare(strict_types=1);

namespace App\Actions\Expenses;

use App\Enums\Currency;
use App\Enums\ExpenseMethod;
use App\Exceptions\Inventory\IdempotencyConflictException;
use App\Models\Expense;
use App\Models\Store;
use App\Models\User;
use App\Services\Idempotency;
use App\Services\Purchasing\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Record a card or bank transfer expense. It appears in expense history only and posts nothing
 * to the cashbox; cash expenses go through StartCashPayout and ConfirmCashPayout instead.
 *
 * The request key replays an identical submission by the same actor and conflicts otherwise.
 */
class RecordExpense
{
    /**
     * @param  array{method?: mixed, category?: mixed, description?: mixed, amount?: mixed, expense_date?: mixed, reference?: mixed}  $input
     *
     * @throws AuthorizationException
     * @throws ValidationException
     * @throws IdempotencyConflictException when the key was used for a different submission.
     */
    public function __invoke(User $actor, Store $store, array $input, string $idempotencyKey): Expense
    {
        Gate::forUser($actor)->authorize('manageExpenses', $store);

        $key = Idempotency::key($idempotencyKey);

        $method = validator($input, [
            'method' => ['required', Rule::in([ExpenseMethod::CARD->value, ExpenseMethod::BANK_TRANSFER->value])],
        ], [], ['method' => __('pos.expenses.fields.method')])->validate()['method'];

        $details = ExpenseInput::from($input);
        $fingerprint = Idempotency::fingerprint([
            'operation' => 'expense',
            'store_id' => $store->id,
            'method' => $method,
            ...$details->fingerprintPayload(),
        ]);

        $existing = Expense::query()->where('idempotency_key', $key)->first();

        if ($existing !== null) {
            return $this->replay($existing, $store, $actor, $fingerprint);
        }

        try {
            return Expense::query()->create([
                'store_id' => $store->id,
                'currency' => Currency::TRY,
                'category' => $details->category,
                'description' => $details->description,
                'amount' => Money::toDecimal($details->amountMinor),
                'expense_date' => $details->expenseDate,
                'method' => $method,
                'reference' => $details->reference,
                'user_id' => $actor->id,
                'idempotency_key' => $key,
                'request_fingerprint' => $fingerprint,
                'recorded_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = Expense::query()->where('idempotency_key', $key)->first();

            if ($existing === null) {
                throw $exception;
            }

            return $this->replay($existing, $store, $actor, $fingerprint);
        }
    }

    /**
     * @throws IdempotencyConflictException
     */
    private function replay(Expense $existing, Store $store, User $actor, string $fingerprint): Expense
    {
        if ($existing->store_id !== $store->id) {
            throw new IdempotencyConflictException;
        }

        Idempotency::assertReplay($existing->user_id, (string) $existing->request_fingerprint, $actor, $fingerprint);

        return $existing;
    }
}
