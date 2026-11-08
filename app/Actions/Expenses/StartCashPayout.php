<?php

declare(strict_types=1);

namespace App\Actions\Expenses;

use App\Enums\CashPayoutStatus;
use App\Exceptions\Inventory\IdempotencyConflictException;
use App\Models\CashboxSession;
use App\Models\CashPayout;
use App\Models\Store;
use App\Models\User;
use App\Services\Idempotency;
use App\Services\Purchasing\LocalizedMoney;
use App\Services\Purchasing\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Reserve a cash expense's amount on the session the owner was shown, before staff are told to
 * hand the cash out. Nothing is posted yet; ConfirmCashPayout records the expense.
 *
 * The store row is locked exclusively as the first statement, as opening and closing do, so
 * payouts are serialized with each other and with closing: the available cash read afterwards
 * includes every committed movement and reservation. The amount may not exceed the expected cash
 * minus pending payouts. The request key replays an identical start, even after the payout
 * completed or was released, and conflicts otherwise.
 */
class StartCashPayout
{
    /**
     * @param  int|null  $sessionId  The open session the form was shown with.
     * @param  array{category?: mixed, description?: mixed, amount?: mixed, expense_date?: mixed, reference?: mixed}  $input
     *
     * @throws AuthorizationException
     * @throws ValidationException
     * @throws IdempotencyConflictException when the key was used for a different submission.
     */
    public function __invoke(User $actor, Store $store, ?int $sessionId, array $input, string $requestKey): CashPayout
    {
        Gate::forUser($actor)->authorize('manageExpenses', $store);

        $key = Idempotency::key($requestKey);
        $details = ExpenseInput::from($input);
        $fingerprint = Idempotency::fingerprint([
            'operation' => 'cash_payout',
            'store_id' => $store->id,
            'session_id' => $sessionId,
            ...$details->fingerprintPayload(),
        ]);

        try {
            return DB::transaction(function () use ($actor, $store, $sessionId, $details, $key, $fingerprint): CashPayout {
                Store::query()->whereKey($store->id)->lockForUpdate()->firstOrFail();

                $existing = CashPayout::query()->where('request_key', $key)->first();

                if ($existing !== null) {
                    return self::replay($existing, $store, $actor, $fingerprint);
                }

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
                    'status' => CashPayoutStatus::PENDING,
                    'request_key' => $key,
                    'request_fingerprint' => $fingerprint,
                    'started_by' => $actor->id,
                    'started_at' => now(),
                ]);
            });
        } catch (UniqueConstraintViolationException $exception) {
            $existing = CashPayout::query()->where('request_key', $key)->first();

            if ($existing === null) {
                throw $exception;
            }

            return self::replay($existing, $store, $actor, $fingerprint);
        }
    }

    /**
     * @throws IdempotencyConflictException
     */
    private static function replay(CashPayout $existing, Store $store, User $actor, string $fingerprint): CashPayout
    {
        if ($existing->store_id !== $store->id) {
            throw new IdempotencyConflictException;
        }

        Idempotency::assertReplay($existing->started_by, $existing->request_fingerprint, $actor, $fingerprint);

        return $existing;
    }
}
