<?php

declare(strict_types=1);

namespace App\Actions\Cashbox;

use App\Enums\CashboxTransferKind;
use App\Enums\CashPayoutStatus;
use App\Exceptions\Inventory\IdempotencyConflictException;
use App\Exceptions\Purchasing\InvalidAmountException;
use App\Models\CashboxSession;
use App\Models\CashboxTransfer;
use App\Models\Store;
use App\Models\User;
use App\Services\Idempotency;
use App\Services\Purchasing\LocalizedMoney;
use App\Services\Purchasing\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Persist an owner deposit or withdrawal as pending on the open session the form was shown with,
 * before the owner is told to move any cash. ConfirmCashboxTransfer posts its movement.
 *
 * A withdrawal may not exceed the cash available after every pending expense, supplier, and
 * withdrawal payout. The store row is locked exclusively as the first statement, as opening,
 * closing, and expense payouts do, and no consistent read precedes it, so the available cash is
 * read after every reservation and close it waited for. The request key is claimed with the
 * transfer; an identical resubmission by the same actor returns the original transfer, with
 * wasRecentlyCreated false, whatever its state; anything else under the key conflicts.
 */
class StartCashboxTransfer
{
    /**
     * @param  array{kind?: mixed, amount?: mixed, reason?: mixed}  $input
     *
     * @throws AuthorizationException
     * @throws ValidationException
     * @throws IdempotencyConflictException when the key was used for a different submission.
     */
    public function __invoke(User $actor, Store $store, ?int $sessionId, array $input, string $requestKey): CashboxTransfer
    {
        Gate::forUser($actor)->authorize('manageCashbox', $store);

        $key = Idempotency::key($requestKey);

        $validated = validator($input, [
            'kind' => ['required', Rule::enum(CashboxTransferKind::class)],
            'amount' => ['required', 'string', 'max:32'],
            'reason' => ['nullable', 'string'],
        ], [], [
            'kind' => __('pos.cashbox.transfers.fields.kind'),
            'amount' => __('pos.cashbox.transfers.fields.amount'),
        ])->validate();

        $kind = CashboxTransferKind::from($validated['kind']);

        try {
            $amountMinor = Money::parse($validated['amount'], Money::MAX_TOTAL_AMOUNT);
        } catch (InvalidAmountException) {
            throw ValidationException::withMessages(['amount' => __('pos.cashbox.transfers.errors.amount_invalid')]);
        }

        $reason = Str::trim((string) ($validated['reason'] ?? ''));

        if ($reason === '' || mb_strlen($reason) > CashboxTransfer::MAX_REASON_LENGTH) {
            throw ValidationException::withMessages(['reason' => __('pos.cashbox.transfers.errors.reason_required', ['max' => CashboxTransfer::MAX_REASON_LENGTH])]);
        }

        $fingerprint = Idempotency::fingerprint([
            'operation' => 'cashbox_transfer',
            'store_id' => $store->id,
            'session_id' => $sessionId,
            'kind' => $kind->value,
            'amount' => $amountMinor,
            'reason' => $reason,
        ]);

        try {
            return DB::transaction(function () use ($actor, $store, $sessionId, $kind, $amountMinor, $reason, $key, $fingerprint): CashboxTransfer {
                Store::query()->whereKey($store->id)->lockForUpdate()->firstOrFail();

                $existing = CashboxTransfer::query()->where('request_key', $key)->first();

                if ($existing !== null) {
                    return self::replay($existing, $store, $actor, $fingerprint);
                }

                $session = CashboxSession::openFor($store);

                if ($session === null) {
                    throw ValidationException::withMessages(['cashbox' => __('pos.cashbox.errors.no_open_session')]);
                }

                if ($session->id !== $sessionId) {
                    throw ValidationException::withMessages(['cashbox' => __('pos.cashbox.transfers.errors.session_changed')]);
                }

                if ($kind === CashboxTransferKind::WITHDRAWAL) {
                    $available = $session->availableForPayoutMinor();

                    if ($amountMinor > $available) {
                        throw ValidationException::withMessages(['amount' => __('pos.cashbox.transfers.errors.exceeds_available', ['available' => LocalizedMoney::format(max(0, $available))])]);
                    }
                }

                return CashboxTransfer::query()->create([
                    'store_id' => $store->id,
                    'cashbox_session_id' => $session->id,
                    'kind' => $kind,
                    'amount' => Money::toDecimal($amountMinor),
                    'reason' => $reason,
                    'request_key' => $key,
                    'request_fingerprint' => $fingerprint,
                    'status' => CashPayoutStatus::PENDING,
                    'started_by' => $actor->id,
                    'started_at' => now(),
                ]);
            });
        } catch (UniqueConstraintViolationException $exception) {
            $existing = CashboxTransfer::query()->where('request_key', $key)->first();

            if ($existing === null) {
                throw $exception;
            }

            return self::replay($existing, $store, $actor, $fingerprint);
        }
    }

    /**
     * @throws IdempotencyConflictException
     */
    private static function replay(CashboxTransfer $existing, Store $store, User $actor, string $fingerprint): CashboxTransfer
    {
        if ($existing->store_id !== $store->id) {
            throw new IdempotencyConflictException;
        }

        Idempotency::assertReplay($existing->started_by, $existing->request_fingerprint, $actor, $fingerprint);

        return $existing;
    }
}
