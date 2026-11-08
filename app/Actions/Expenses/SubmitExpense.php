<?php

declare(strict_types=1);

namespace App\Actions\Expenses;

use App\Enums\CashPayoutStatus;
use App\Enums\Currency;
use App\Enums\ExpenseMethod;
use App\Exceptions\Inventory\IdempotencyConflictException;
use App\Models\CashboxSession;
use App\Models\CashPayout;
use App\Models\Expense;
use App\Models\ExpenseSubmission;
use App\Models\InventoryUnit;
use App\Models\OperationalSupplier;
use App\Models\Store;
use App\Models\User;
use App\Services\Idempotency;
use App\Services\Purchasing\LocalizedMoney;
use App\Services\Purchasing\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Submit the expense form once per request key, whichever method it used.
 *
 * Card and bank transfer expenses are recorded at once and post nothing to the cashbox. A cash
 * expense only reserves its amount as a pending payout on the session the form was shown with,
 * within the expected cash minus pending payouts; ConfirmCashPayout records the expense once the
 * cash is handed out.
 *
 * The store row is locked exclusively, as opening and closing do, so submissions are serialized
 * with each other and with closing. Lock order: the linked device's unit, as a checkout locks
 * its units before the store row; the linked supplier in share mode; then the store row. Both
 * references are checked under those locks. The
 * request key is claimed in expense_submissions together with the payout or expense, and its
 * unique index refuses a second claim from any concurrent transaction. An existing request is
 * resolved before the supplier and device are checked, so an identical resubmission by the same
 * actor returns the original submission, with wasRecentlyCreated false, even after its payout
 * completed or was released, its supplier was deactivated, or its device moved; anything else
 * under the key, including another method, conflicts.
 */
class SubmitExpense
{
    /**
     * @param  int|null  $sessionId  The open session the form was shown with; used for cash only.
     * @param  array{method?: mixed, category?: mixed, description?: mixed, amount?: mixed, expense_date?: mixed, reference?: mixed, operational_supplier_id?: mixed, inventory_unit_id?: mixed}  $input
     *
     * @throws AuthorizationException
     * @throws ValidationException
     * @throws IdempotencyConflictException when the key was used for a different submission.
     */
    public function __invoke(User $actor, Store $store, ?int $sessionId, array $input, string $requestKey): ExpenseSubmission
    {
        Gate::forUser($actor)->authorize('manageExpenses', $store);

        $key = Idempotency::key($requestKey);

        $method = ExpenseMethod::from(validator($input, [
            'method' => ['required', Rule::enum(ExpenseMethod::class)],
        ], [], ['method' => __('pos.expenses.fields.method')])->validate()['method']);

        $details = ExpenseInput::from($input);
        $sessionId = $method === ExpenseMethod::CASH ? $sessionId : null;
        $fingerprint = Idempotency::fingerprint([
            'operation' => 'expense_submission',
            'store_id' => $store->id,
            'method' => $method->value,
            'session_id' => $sessionId,
            ...$details->fingerprintPayload(),
        ]);

        $existing = ExpenseSubmission::query()->where('request_key', $key)->first();

        if ($existing !== null) {
            return self::replay($existing, $store, $actor, $fingerprint);
        }

        try {
            return DB::transaction(function () use ($actor, $store, $method, $sessionId, $details, $key, $fingerprint): ExpenseSubmission {
                $unit = $details->unitId === null ? null : InventoryUnit::query()->whereKey($details->unitId)->lockForUpdate()->first();
                $supplier = $details->supplierId === null ? null : OperationalSupplier::query()->whereKey($details->supplierId)->sharedLock()->first();
                Store::query()->whereKey($store->id)->lockForUpdate()->firstOrFail();

                $existing = ExpenseSubmission::query()->where('request_key', $key)->first();

                if ($existing !== null) {
                    return self::replay($existing, $store, $actor, $fingerprint);
                }

                $details->assertReferencesEligible($actor, $store, $unit, $supplier);

                $now = now();
                $payout = $method === ExpenseMethod::CASH ? self::reserve($actor, $store, $sessionId, $details, $now) : null;
                $expense = $payout === null ? Expense::query()->create([
                    'store_id' => $store->id,
                    'currency' => Currency::TRY,
                    'category' => $details->category,
                    'description' => $details->description,
                    'amount' => Money::toDecimal($details->amountMinor),
                    'expense_date' => $details->expenseDate,
                    'method' => $method,
                    'reference' => $details->reference,
                    'operational_supplier_id' => $details->supplierId,
                    'inventory_unit_id' => $details->unitId,
                    'user_id' => $actor->id,
                    'recorded_at' => $now,
                ]) : null;

                return ExpenseSubmission::query()->create([
                    'store_id' => $store->id,
                    'request_key' => $key,
                    'method' => $method,
                    'request_fingerprint' => $fingerprint,
                    'user_id' => $actor->id,
                    'cash_payout_id' => $payout?->id,
                    'expense_id' => $expense?->id,
                    'submitted_at' => $now,
                ]);
            });
        } catch (UniqueConstraintViolationException $exception) {
            $existing = ExpenseSubmission::query()->where('request_key', $key)->first();

            if ($existing === null) {
                throw $exception;
            }

            return self::replay($existing, $store, $actor, $fingerprint);
        }
    }

    /**
     * @throws ValidationException
     */
    private static function reserve(User $actor, Store $store, ?int $sessionId, ExpenseInput $details, mixed $now): CashPayout
    {
        $session = CashboxSession::openFor($store);

        if ($session === null) {
            throw ValidationException::withMessages(['cashbox' => __('pos.cashbox.errors.no_open_session')]);
        }

        if ($session->id !== $sessionId) {
            throw ValidationException::withMessages(['cashbox' => __('pos.expenses.errors.session_changed')]);
        }

        $available = $session->availableForPayoutMinor();

        if ($details->amountMinor > $available) {
            throw ValidationException::withMessages(['amount' => __('pos.expenses.errors.exceeds_available', ['available' => LocalizedMoney::format(max(0, $available))])]);
        }

        return CashPayout::query()->create([
            'store_id' => $store->id,
            'cashbox_session_id' => $session->id,
            'amount' => Money::toDecimal($details->amountMinor),
            'category' => $details->category,
            'description' => $details->description,
            'expense_date' => $details->expenseDate,
            'reference' => $details->reference,
            'operational_supplier_id' => $details->supplierId,
            'inventory_unit_id' => $details->unitId,
            'status' => CashPayoutStatus::PENDING,
            'started_by' => $actor->id,
            'started_at' => $now,
        ]);
    }

    /**
     * @throws IdempotencyConflictException
     */
    private static function replay(ExpenseSubmission $existing, Store $store, User $actor, string $fingerprint): ExpenseSubmission
    {
        if ($existing->store_id !== $store->id) {
            throw new IdempotencyConflictException;
        }

        Idempotency::assertReplay($existing->user_id, $existing->request_fingerprint, $actor, $fingerprint);

        return $existing;
    }
}
