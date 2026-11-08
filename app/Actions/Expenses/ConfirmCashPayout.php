<?php

declare(strict_types=1);

namespace App\Actions\Expenses;

use App\Enums\CashMovementDirection;
use App\Enums\CashMovementType;
use App\Enums\CashPayoutStatus;
use App\Enums\Currency;
use App\Enums\ExpenseMethod;
use App\Models\CashboxSession;
use App\Models\CashMovement;
use App\Models\CashPayout;
use App\Models\Expense;
use App\Models\InventoryUnit;
use App\Models\Store;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Confirm that a pending payout's cash was handed out: record its cash expense and one outgoing
 * movement on its session, and complete the payout, in one transaction.
 *
 * The confirmation names the payout and the session the screen displayed; a payout from another
 * session or branch is refused, and a released payout is never replaced by another. Confirming
 * a completed payout again returns its expense without writing, so a retry after a lost
 * response is safe. Lock order: the payout's linked device unit, as a checkout locks its units
 * before the store row; the store row exclusively; then the payout, whose stored device
 * reference is rechecked under lock.
 */
class ConfirmCashPayout
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $actor, Store $store, int $payoutId, ?int $sessionId): Expense
    {
        Gate::forUser($actor)->authorize('manageExpenses', $store);

        $unitId = CashPayout::query()->where('store_id', $store->id)->findOrFail($payoutId)->inventory_unit_id;

        return DB::transaction(function () use ($actor, $store, $payoutId, $sessionId, $unitId): Expense {
            if ($unitId !== null) {
                InventoryUnit::query()->whereKey($unitId)->lockForUpdate()->first();
            }

            Store::query()->whereKey($store->id)->lockForUpdate()->firstOrFail();

            $payout = CashPayout::query()->where('store_id', $store->id)->lockForUpdate()->findOrFail($payoutId);

            if ($payout->inventory_unit_id !== $unitId) {
                throw new LogicException("Cash payout {$payout->id} changed its device reference.");
            }

            if ($payout->cashbox_session_id !== $sessionId) {
                throw ValidationException::withMessages(['cashbox' => __('pos.expenses.errors.payout_changed')]);
            }

            if ($payout->status === CashPayoutStatus::COMPLETED) {
                return $payout->expense()->firstOrFail();
            }

            if (! $payout->isPending()) {
                throw ValidationException::withMessages(['cashbox' => __('pos.expenses.errors.payout_not_pending')]);
            }

            $session = CashboxSession::openFor($store);

            if ($session?->id !== $payout->cashbox_session_id) {
                throw new LogicException("Cash payout {$payout->id} is pending on a session that is not open.");
            }

            $now = now();

            $expense = Expense::query()->create([
                'store_id' => $store->id,
                'currency' => Currency::TRY,
                'category' => $payout->category,
                'description' => $payout->description,
                'amount' => $payout->amount,
                'expense_date' => $payout->expense_date->toDateString(),
                'method' => ExpenseMethod::CASH,
                'reference' => $payout->reference,
                'operational_supplier_id' => $payout->operational_supplier_id,
                'inventory_unit_id' => $payout->inventory_unit_id,
                'user_id' => $actor->id,
                'cash_payout_id' => $payout->id,
                'recorded_at' => $now,
            ]);

            CashMovement::query()->create([
                'store_id' => $store->id,
                'cashbox_session_id' => $session->id,
                'type' => CashMovementType::EXPENSE_CASH,
                'direction' => CashMovementDirection::OUT,
                'amount' => $payout->amount,
                'cash_payout_id' => $payout->id,
                'expense_id' => $expense->id,
                'user_id' => $actor->id,
                'occurred_at' => $now,
            ]);

            $payout->update([
                'status' => CashPayoutStatus::COMPLETED,
                'completed_at' => $now,
            ]);

            return $expense;
        });
    }
}
