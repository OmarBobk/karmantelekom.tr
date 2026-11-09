<?php

declare(strict_types=1);

namespace App\Actions\Cashbox;

use App\Actions\Network\ReservationGuard;
use App\Enums\CashIntakeSource;
use App\Enums\CashIntakeStatus;
use App\Models\CashIntake;
use App\Models\CheckoutAttempt;
use App\Models\Customer;
use App\Models\Store;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Release a pending cash intake whose sale or payment cannot complete. Owner only, with a
 * required note, logged; nothing is posted and the intake is kept as released.
 *
 * Use it after confirming the cash was not kept (never taken, or handed back). A released sale
 * intake unfreezes its checkout unless a card payment holds it, so the cashier can edit, cancel,
 * or bind cash again. Lock order: the source (attempt or customer row), then the intake.
 */
class ReleaseCashIntake
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $actor, Store $store, int $intakeId, ?string $note): CashIntake
    {
        Gate::forUser($actor)->authorize('manageCashbox', $store);

        $intake = CashIntake::query()->where('store_id', $store->id)->findOrFail($intakeId);
        $note = Str::trim((string) $note);

        if ($note === '' || mb_strlen($note) > CashIntake::MAX_NOTE_LENGTH) {
            throw ValidationException::withMessages(['release_note' => __('pos.cashbox.errors.release_note_required', ['max' => CashIntake::MAX_NOTE_LENGTH])]);
        }

        return DB::transaction(function () use ($actor, $store, $intake, $note): CashIntake {
            $attempt = null;

            if ($intake->source_type === CashIntakeSource::SALE) {
                $attempt = CheckoutAttempt::query()->whereKey($intake->checkout_attempt_id)->lockForUpdate()->firstOrFail();
            } else {
                Customer::query()->whereKey($intake->customer_id)->lockForUpdate()->firstOrFail();
            }

            $locked = CashIntake::query()->whereKey($intake->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPending()) {
                if ($locked->status === CashIntakeStatus::RELEASED && $locked->released_by === $actor->id && $locked->release_note === $note) {
                    return $locked;
                }

                throw ValidationException::withMessages(['cashbox' => __('pos.cashbox.errors.intake_not_pending')]);
            }

            $locked->update([
                'status' => CashIntakeStatus::RELEASED,
                'pending_source' => null,
                'released_by' => $actor->id,
                'released_at' => now(),
                'release_note' => $note,
            ]);

            if ($attempt !== null && $attempt->isOpen() && ! $attempt->hasStartedTerminalPayment() && ! $attempt->cardApproval()->exists()) {
                $attempt->update(['frozen_at' => null]);
                ReservationGuard::releaseClaim($attempt, $actor);
            }

            activity('cashbox')
                ->performedOn($locked)
                ->causedBy($actor)
                ->event('cash_intake_released')
                ->withProperties([
                    'store_id' => $store->id,
                    'cashbox_session_id' => $locked->cashbox_session_id,
                    'source_type' => $locked->source_type->value,
                    'checkout_attempt_id' => $locked->checkout_attempt_id,
                    'customer_id' => $locked->customer_id,
                    'amount' => $locked->amount,
                    'started_by' => $locked->started_by,
                    'started_at' => $locked->started_at->toIso8601String(),
                    'note' => $note,
                ])
                ->log("Cash intake {$locked->id} of {$locked->amount} released");

            return $locked;
        });
    }
}
