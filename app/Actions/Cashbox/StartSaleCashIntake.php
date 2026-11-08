<?php

declare(strict_types=1);

namespace App\Actions\Cashbox;

use App\Actions\Network\ReservationGuard;
use App\Actions\Pos\FinalizeCheckout;
use App\Actions\Pos\ReviewCheckout;
use App\Actions\Pos\StartTerminalPayment;
use App\Enums\CardApprovalStatus;
use App\Enums\CashIntakeSource;
use App\Models\CashIntake;
use App\Models\CheckoutAttempt;
use App\Models\Store;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Bind a checkout's cash to the open cashbox session before the cashier is told to take it.
 *
 * The amount is the reviewed total less credit and any approved card amount, so a mixed checkout
 * keeps its card approval and binds only the remainder. The reviewed cart freezes: the amount
 * cannot change until the sale completes or the owner releases the intake. A card cannot be
 * started afterwards, and a pending terminal payment must be settled first.
 *
 * Lock order: the attempt, then the store row in share mode with its open session. The request key makes a resent
 * start return the same intake.
 */
class StartSaleCashIntake
{
    public function __construct(private readonly ReservationGuard $reservationGuard) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $actor, Store $store, string $attemptKey, int $expectedRevision, string $requestKey): CashIntake
    {
        Gate::forUser($actor)->authorize('usePos', $store);

        $attempt = ReviewCheckout::findAttempt($store, $attemptKey);
        Gate::forUser($actor)->authorize('operate', $attempt);

        if (! Str::isUuid($requestKey)) {
            throw ValidationException::withMessages(['attempt' => __('pos.errors.attempt_changed')]);
        }

        return DB::transaction(function () use ($actor, $store, $attempt, $expectedRevision, $requestKey): CashIntake {
            $locked = CheckoutAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            $existing = CashIntake::query()->where('request_key', $requestKey)->first();

            if ($existing !== null) {
                if ($existing->checkout_attempt_id !== $locked->id || $existing->started_by !== $actor->id) {
                    throw ValidationException::withMessages(['attempt' => __('pos.errors.attempt_changed')]);
                }

                return $existing;
            }

            ReviewCheckout::assertOpen($locked);

            if ($locked->revision !== $expectedRevision) {
                throw ValidationException::withMessages(['attempt' => __('pos.errors.attempt_changed')]);
            }

            if ($locked->hasPendingCashIntake()) {
                throw ValidationException::withMessages(['cashbox' => __('pos.cashbox.errors.intake_already_pending')]);
            }

            $approval = $locked->cardApproval()->first();

            if ($approval === null && $locked->hasStartedTerminalPayment()) {
                throw ValidationException::withMessages(['attempt' => __('pos.errors.terminal_payment_pending')]);
            }

            if ($locked->creditMinor() > 0 && ! $locked->isFrozen()) {
                ReviewCheckout::assertCreditAllowed($actor, $store, $locked->customer_id);
            }

            $cardMinor = $approval !== null && $approval->status === CardApprovalStatus::APPROVED ? $approval->amountMinor() : 0;
            $cashDue = $locked->payableMinor() - $cardMinor;

            if ($cashDue <= 0) {
                throw ValidationException::withMessages(['cash_tendered' => __('pos.errors.no_cash_due')]);
            }

            if (! $locked->isFrozen()) {
                StartTerminalPayment::assertReviewedPricesCurrent($actor, $locked);
                $this->reservationGuard->lockForPaymentStart($locked);
                $locked->update(['frozen_at' => now()]);
            }

            return CashIntakePosting::bind($actor, $store, CashIntakeSource::SALE, $locked->id, $cashDue, $requestKey);
        }, FinalizeCheckout::DEADLOCK_ATTEMPTS);
    }
}
