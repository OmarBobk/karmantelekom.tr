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
 * Open the store's cashbox session with the cash the owner counted in the drawer.
 *
 * The count is compared with the previous session's closing count; a difference needs a note.
 * The store row is locked first so competing opens are serialized; open_store_id's unique index
 * backs the one-open-session rule. A resent identical open by the same owner returns the open
 * session instead of failing.
 */
class OpenCashboxSession
{
    /**
     * @param  string|null  $counted  Canonical decimal.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $actor, Store $store, ?string $counted, ?string $note): CashboxSession
    {
        Gate::forUser($actor)->authorize('manageCashbox', $store);

        if (Str::trim((string) $counted) === '') {
            throw ValidationException::withMessages(['opening_counted' => __('pos.cashbox.errors.count_required')]);
        }

        try {
            $countedMinor = Money::parse((string) $counted, Money::MAX_TOTAL_AMOUNT, allowZero: true);
        } catch (InvalidAmountException) {
            throw ValidationException::withMessages(['opening_counted' => __('pos.cashbox.errors.count_invalid')]);
        }

        $note = Str::trim((string) $note);

        if (mb_strlen($note) > CashboxSession::MAX_NOTE_LENGTH) {
            throw ValidationException::withMessages(['opening_note' => __('pos.cashbox.errors.note_too_long', ['max' => CashboxSession::MAX_NOTE_LENGTH])]);
        }

        return DB::transaction(function () use ($actor, $store, $countedMinor, $note): CashboxSession {
            Store::query()->whereKey($store->id)->lockForUpdate()->firstOrFail();

            $open = CashboxSession::openFor($store);

            if ($open !== null) {
                if ($open->opened_by === $actor->id && $open->openingCountedMinor() === $countedMinor && (string) $open->opening_note === $note) {
                    return $open;
                }

                throw ValidationException::withMessages(['cashbox' => __('pos.cashbox.errors.already_open')]);
            }

            $previous = CashboxSession::query()->where('store_id', $store->id)->closed()->latest('closed_at')->latest('id')->first();
            $expected = $previous?->countedClosingMinor();
            $difference = $expected === null ? null : $countedMinor - $expected;

            if ($difference !== null && $difference !== 0 && $note === '') {
                throw ValidationException::withMessages(['opening_note' => __('pos.cashbox.errors.opening_note_required', [
                    'expected' => LocalizedMoney::format($expected),
                    'difference' => LocalizedMoney::format($difference),
                ])]);
            }

            return CashboxSession::query()->create([
                'store_id' => $store->id,
                'open_store_id' => $store->id,
                'status' => CashboxSessionStatus::OPEN,
                'opened_by' => $actor->id,
                'opened_at' => now(),
                'opening_expected' => $expected === null ? null : Money::toDecimal($expected),
                'opening_counted' => Money::toDecimal($countedMinor),
                'opening_difference' => $difference === null ? null : Money::toDecimal($difference),
                'opening_note' => $note === '' ? null : $note,
            ]);
        });
    }
}
