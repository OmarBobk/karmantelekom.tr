<?php

declare(strict_types=1);

namespace App\Actions\Purchasing;

use App\Enums\CashPayoutResolution;
use App\Enums\CashPayoutStatus;
use App\Models\CashPayout;
use App\Models\Purchase;
use App\Models\Store;
use App\Models\SupplierCashPayout;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Release a pending supplier cash payout after the owner verified its cash was not handed out,
 * or was handed back. Needs the branch's cashbox permission, the verified resolution, and a
 * required note, and is logged. Nothing is posted and no payment is recorded; the drawer and
 * invoice reservations end and the payout is kept as released. Locks the store row exclusively
 * first, then the payout, as confirming does.
 */
class ReleaseSupplierCashPayout
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $actor, Purchase $purchase, int $payoutId, ?string $resolution, ?string $note): SupplierCashPayout
    {
        Gate::forUser($actor)->authorize('manageCashbox', $purchase->store);

        $payout = SupplierCashPayout::query()->where('purchase_id', $purchase->id)->findOrFail($payoutId);

        validator(['resolution' => $resolution], [
            'resolution' => ['required', Rule::enum(CashPayoutResolution::class)],
        ], [], ['resolution' => __('pos.expenses.fields.resolution')])->validate();

        $resolution = CashPayoutResolution::from((string) $resolution);
        $note = Str::trim((string) $note);

        if ($note === '' || mb_strlen($note) > CashPayout::MAX_NOTE_LENGTH) {
            throw ValidationException::withMessages(['release_note' => __('pos.cashbox.errors.release_note_required', ['max' => CashPayout::MAX_NOTE_LENGTH])]);
        }

        return DB::transaction(function () use ($actor, $purchase, $payout, $resolution, $note): SupplierCashPayout {
            Store::query()->whereKey($purchase->store_id)->lockForUpdate()->firstOrFail();

            $locked = SupplierCashPayout::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPending()) {
                if ($locked->status === CashPayoutStatus::RELEASED && $locked->released_by === $actor->id && $locked->resolution === $resolution && $locked->release_note === $note) {
                    return $locked;
                }

                throw ValidationException::withMessages(['cashbox' => __('purchases.cash_payouts.errors.payout_not_pending')]);
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
                ->event('supplier_cash_payout_released')
                ->withProperties([
                    'store_id' => $locked->store_id,
                    'purchase_id' => $locked->purchase_id,
                    'cashbox_session_id' => $locked->cashbox_session_id,
                    'amount' => $locked->amount,
                    'reference' => $locked->reference,
                    'started_by' => $locked->started_by,
                    'started_at' => $locked->started_at->toIso8601String(),
                    'resolution' => $resolution->value,
                    'note' => $note,
                ])
                ->log("Supplier cash payout {$locked->id} of {$locked->amount} released");

            return $locked;
        });
    }
}
