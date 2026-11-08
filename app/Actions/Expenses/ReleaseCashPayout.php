<?php

declare(strict_types=1);

namespace App\Actions\Expenses;

use App\Enums\CashPayoutResolution;
use App\Enums\CashPayoutStatus;
use App\Models\CashPayout;
use App\Models\Store;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Release a pending cash payout after the owner verified its cash was not handed out, or was
 * handed back. Owner only, with the verified resolution and a required note, logged. Nothing is
 * posted and no expense is recorded; the reservation ends and the payout is kept as released.
 * The store row is locked exclusively first, then the payout, as ConfirmCashPayout does.
 */
class ReleaseCashPayout
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $actor, Store $store, int $payoutId, ?string $resolution, ?string $note): CashPayout
    {
        Gate::forUser($actor)->authorize('manageCashbox', $store);

        $payout = CashPayout::query()->where('store_id', $store->id)->findOrFail($payoutId);

        validator(['resolution' => $resolution], [
            'resolution' => ['required', Rule::enum(CashPayoutResolution::class)],
        ], [], ['resolution' => __('pos.expenses.fields.resolution')])->validate();

        $resolution = CashPayoutResolution::from((string) $resolution);
        $note = Str::trim((string) $note);

        if ($note === '' || mb_strlen($note) > CashPayout::MAX_NOTE_LENGTH) {
            throw ValidationException::withMessages(['release_note' => __('pos.cashbox.errors.release_note_required', ['max' => CashPayout::MAX_NOTE_LENGTH])]);
        }

        return DB::transaction(function () use ($actor, $store, $payout, $resolution, $note): CashPayout {
            Store::query()->whereKey($store->id)->lockForUpdate()->firstOrFail();

            $locked = CashPayout::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPending()) {
                if ($locked->status === CashPayoutStatus::RELEASED && $locked->released_by === $actor->id && $locked->resolution === $resolution && $locked->release_note === $note) {
                    return $locked;
                }

                throw ValidationException::withMessages(['cashbox' => __('pos.expenses.errors.payout_not_pending')]);
            }

            $locked->update([
                'status' => CashPayoutStatus::RELEASED,
                'released_by' => $actor->id,
                'released_at' => now(),
                'resolution' => $resolution,
                'release_note' => $note,
            ]);

            activity('cashbox')
                ->performedOn($locked)
                ->causedBy($actor)
                ->event('cash_payout_released')
                ->withProperties([
                    'store_id' => $store->id,
                    'cashbox_session_id' => $locked->cashbox_session_id,
                    'amount' => $locked->amount,
                    'category' => $locked->category->value,
                    'description' => $locked->description,
                    'started_by' => $locked->started_by,
                    'started_at' => $locked->started_at->toIso8601String(),
                    'resolution' => $resolution->value,
                    'note' => $note,
                ])
                ->log("Cash payout {$locked->id} of {$locked->amount} released");

            return $locked;
        });
    }
}
