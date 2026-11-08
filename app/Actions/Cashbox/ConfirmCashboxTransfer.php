<?php

declare(strict_types=1);

namespace App\Actions\Cashbox;

use App\Enums\CashPayoutStatus;
use App\Models\CashboxSession;
use App\Models\CashboxTransfer;
use App\Models\CashMovement;
use App\Models\Store;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Confirm that the owner moved a pending transfer's cash: post its one deposit or withdrawal
 * movement on its session and complete the transfer, in one transaction.
 *
 * The confirmation names the transfer and the session the screen displayed; a transfer from
 * another session or branch is refused, and a released transfer is never replaced. Confirming a
 * completed transfer again returns its movement without writing. Locks the store row exclusively
 * first, then the transfer, as starting and releasing do.
 */
class ConfirmCashboxTransfer
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $actor, Store $store, int $transferId, ?int $sessionId): CashMovement
    {
        Gate::forUser($actor)->authorize('manageCashbox', $store);

        return DB::transaction(function () use ($actor, $store, $transferId, $sessionId): CashMovement {
            Store::query()->whereKey($store->id)->lockForUpdate()->firstOrFail();

            $transfer = CashboxTransfer::query()->where('store_id', $store->id)->lockForUpdate()->findOrFail($transferId);

            if ($transfer->cashbox_session_id !== $sessionId) {
                throw ValidationException::withMessages(['cashbox' => __('pos.cashbox.transfers.errors.transfer_changed')]);
            }

            if ($transfer->status === CashPayoutStatus::COMPLETED) {
                return $transfer->movement()->firstOrFail();
            }

            if (! $transfer->isPending()) {
                throw ValidationException::withMessages(['cashbox' => __('pos.cashbox.transfers.errors.not_pending')]);
            }

            $session = CashboxSession::openFor($store);

            if ($session?->id !== $transfer->cashbox_session_id) {
                throw new LogicException("Cashbox transfer {$transfer->id} is pending on a session that is not open.");
            }

            $now = now();
            $type = $transfer->kind->movementType();

            $movement = CashMovement::query()->create([
                'store_id' => $store->id,
                'cashbox_session_id' => $session->id,
                'type' => $type,
                'direction' => $type->direction(),
                'amount' => $transfer->amount,
                'cashbox_transfer_id' => $transfer->id,
                'user_id' => $actor->id,
                'occurred_at' => $now,
            ]);

            $transfer->update([
                'status' => CashPayoutStatus::COMPLETED,
                'completed_at' => $now,
            ]);

            return $movement;
        });
    }
}
