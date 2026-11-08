<?php

declare(strict_types=1);

namespace App\Actions\Cashbox;

use App\Enums\CashPayoutResolution;
use App\Enums\CashPayoutStatus;
use App\Models\CashboxTransfer;
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
 * Release a pending transfer after the owner verified its cash was never moved, or was moved
 * back. Owner only, with the verified resolution and a required note, logged. Nothing is posted;
 * the transfer is kept as released and a pending withdrawal's reservation ends. Locks the store
 * row exclusively first, then the transfer, as confirming does.
 */
class ReleaseCashboxTransfer
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $actor, Store $store, int $transferId, ?string $resolution, ?string $note): CashboxTransfer
    {
        Gate::forUser($actor)->authorize('manageCashbox', $store);

        $transfer = CashboxTransfer::query()->where('store_id', $store->id)->findOrFail($transferId);

        validator(['resolution' => $resolution], [
            'resolution' => ['required', Rule::enum(CashPayoutResolution::class)],
        ], [], ['resolution' => __('pos.expenses.fields.resolution')])->validate();

        $resolution = CashPayoutResolution::from((string) $resolution);
        $note = Str::trim((string) $note);

        if ($note === '' || mb_strlen($note) > CashPayout::MAX_NOTE_LENGTH) {
            throw ValidationException::withMessages(['release_note' => __('pos.cashbox.errors.release_note_required', ['max' => CashPayout::MAX_NOTE_LENGTH])]);
        }

        return DB::transaction(function () use ($actor, $store, $transfer, $resolution, $note): CashboxTransfer {
            Store::query()->whereKey($store->id)->lockForUpdate()->firstOrFail();

            $locked = CashboxTransfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPending()) {
                if ($locked->status === CashPayoutStatus::RELEASED && $locked->released_by === $actor->id && $locked->resolution === $resolution && $locked->release_note === $note) {
                    return $locked;
                }

                throw ValidationException::withMessages(['cashbox' => __('pos.cashbox.transfers.errors.not_pending')]);
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
                ->event('cashbox_transfer_released')
                ->withProperties([
                    'store_id' => $store->id,
                    'cashbox_session_id' => $locked->cashbox_session_id,
                    'kind' => $locked->kind->value,
                    'amount' => $locked->amount,
                    'reason' => $locked->reason,
                    'started_by' => $locked->started_by,
                    'started_at' => $locked->started_at->toIso8601String(),
                    'resolution' => $resolution->value,
                    'note' => $note,
                ])
                ->log("Cashbox {$locked->kind->value} {$locked->id} of {$locked->amount} released");

            return $locked;
        });
    }
}
