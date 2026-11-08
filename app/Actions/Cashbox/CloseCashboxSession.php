<?php

declare(strict_types=1);

namespace App\Actions\Cashbox;

use App\Enums\CashboxSessionStatus;
use App\Exceptions\Purchasing\InvalidAmountException;
use App\Models\CashboxSession;
use App\Models\Store;
use App\Models\User;
use App\Services\Purchasing\LocalizedMoney;
use App\Services\Purchasing\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Close the store's open session with the cash the owner counted.
 *
 * The store row is locked exclusively as the transaction's first statement; cash postings take it
 * in share mode as their last lock (see CashboxSession::lockOpenShared). The expected amount read
 * afterwards includes every committed movement, and postings wait until the close commits (then
 * find no open session). A pending cash intake blocks closing: that cash may be in
 * the drawer without its sale or payment. The expected amount the owner was shown must still be
 * current, otherwise the count is refused as stale. A difference needs a note. A resent identical
 * close by the same owner returns the closed session.
 */
class CloseCashboxSession
{
    /**
     * @param  string|null  $expectedShown  Canonical decimal the owner saw when counting.
     * @param  string|null  $counted  Canonical decimal.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $actor, Store $store, int $sessionId, ?string $expectedShown, ?string $counted, ?string $note): CashboxSession
    {
        Gate::forUser($actor)->authorize('manageCashbox', $store);

        if (Str::trim((string) $counted) === '') {
            throw ValidationException::withMessages(['counted_closing' => __('pos.cashbox.errors.count_required')]);
        }

        try {
            $countedMinor = Money::parse((string) $counted, Money::MAX_TOTAL_AMOUNT, allowZero: true);
            $shownMinor = Money::parse((string) $expectedShown, Money::MAX_TOTAL_AMOUNT, allowZero: true);
        } catch (InvalidAmountException) {
            throw ValidationException::withMessages(['counted_closing' => __('pos.cashbox.errors.count_invalid')]);
        }

        $note = Str::trim((string) $note);

        if (mb_strlen($note) > CashboxSession::MAX_NOTE_LENGTH) {
            throw ValidationException::withMessages(['closing_note' => __('pos.cashbox.errors.note_too_long', ['max' => CashboxSession::MAX_NOTE_LENGTH])]);
        }

        return DB::transaction(function () use ($actor, $store, $sessionId, $shownMinor, $countedMinor, $note): CashboxSession {
            Store::query()->whereKey($store->id)->lockForUpdate()->firstOrFail();
            $session = CashboxSession::query()->where('open_store_id', $store->id)->lockForUpdate()->first();

            if ($session?->id !== $sessionId) {
                $closed = CashboxSession::query()->where('store_id', $store->id)->findOrFail($sessionId);

                if (! $closed->isOpen()
                    && $closed->closed_by === $actor->id
                    && Money::fromDecimal((string) $closed->expected_closing) === $shownMinor
                    && $closed->countedClosingMinor() === $countedMinor
                    && (string) $closed->closing_note === $note) {
                    return $closed;
                }

                throw ValidationException::withMessages(['cashbox' => __('pos.cashbox.errors.already_closed')]);
            }

            $pending = $session->pendingIntakeCount();

            if ($pending > 0) {
                throw ValidationException::withMessages(['cashbox' => trans_choice('pos.cashbox.errors.pending_intakes', $pending, ['count' => $pending])]);
            }

            $pendingPayouts = $session->pendingPayoutCount();

            if ($pendingPayouts > 0) {
                throw ValidationException::withMessages(['cashbox' => trans_choice('pos.cashbox.errors.pending_payouts', $pendingPayouts, ['count' => $pendingPayouts])]);
            }

            $pendingTransfers = $session->pendingTransferCount();

            if ($pendingTransfers > 0) {
                throw ValidationException::withMessages(['cashbox' => trans_choice('pos.cashbox.errors.pending_transfers', $pendingTransfers, ['count' => $pendingTransfers])]);
            }

            $expected = $session->expectedMinor();

            if ($expected !== $shownMinor) {
                throw ValidationException::withMessages(['counted_closing' => __('pos.cashbox.errors.expected_changed', ['expected' => LocalizedMoney::format($expected)])]);
            }

            $difference = $countedMinor - $expected;

            if ($difference !== 0 && $note === '') {
                throw ValidationException::withMessages(['closing_note' => __('pos.cashbox.errors.closing_note_required', ['difference' => LocalizedMoney::format($difference)])]);
            }

            $session->update([
                'status' => CashboxSessionStatus::CLOSED,
                'open_store_id' => null,
                'closed_by' => $actor->id,
                'closed_at' => now(),
                'expected_closing' => Money::toDecimal($expected),
                'counted_closing' => Money::toDecimal($countedMinor),
                'closing_difference' => Money::toDecimal($difference),
                'closing_note' => $note === '' ? null : $note,
            ]);

            return $session;
        });
    }
}
