<?php

declare(strict_types=1);

namespace App\Actions\Pos;

use App\Exceptions\Purchasing\InvalidAmountException;
use App\Models\CheckoutAttempt;
use App\Models\Store;
use App\Models\User;
use App\Services\Purchasing\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Record that the cashier is about to charge a card on an external terminal.
 *
 * Saved before the counter tells the cashier to charge, so the interval between charging and
 * recording the approval is durable: the reviewed cart freezes, and until the matching approval
 * is recorded or the owner confirms on the terminal that nothing was charged, the attempt can be
 * neither edited, cancelled, nor completed with cash only. Starting the same payment again
 * returns the attempt unchanged; a different terminal or amount is refused.
 */
class StartTerminalPayment
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $actor, Store $store, string $attemptKey, int $expectedRevision, int $terminalId, ?string $amount): CheckoutAttempt
    {
        Gate::forUser($actor)->authorize('usePos', $store);

        $attempt = ReviewCheckout::findAttempt($store, $attemptKey);
        Gate::forUser($actor)->authorize('operate', $attempt);

        try {
            $amountMinor = Money::parse((string) $amount, Money::MAX_TOTAL_AMOUNT);
        } catch (InvalidAmountException) {
            throw ValidationException::withMessages(['card_amount' => __('pos.errors.card_amount_invalid')]);
        }

        $terminal = $store->cardTerminals()->active()->whereKey($terminalId)->first();

        if ($terminal === null) {
            throw ValidationException::withMessages(['card_terminal' => __('pos.errors.terminal_unavailable')]);
        }

        return DB::transaction(function () use ($actor, $attempt, $expectedRevision, $terminal, $amountMinor): CheckoutAttempt {
            $locked = CheckoutAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            ReviewCheckout::assertOpen($locked);

            if ($locked->hasStartedTerminalPayment()) {
                $isSamePayment = $locked->terminal_payment_terminal_id === $terminal->id
                    && $locked->terminalPaymentAmountMinor() === $amountMinor;

                if (! $isSamePayment) {
                    throw ValidationException::withMessages(['attempt' => __('pos.errors.terminal_payment_already_started')]);
                }

                return $locked;
            }

            if ($locked->cardApproval()->exists()) {
                throw ValidationException::withMessages(['attempt' => __('pos.errors.approval_already_recorded')]);
            }

            if ($locked->revision !== $expectedRevision) {
                throw ValidationException::withMessages(['attempt' => __('pos.errors.attempt_changed')]);
            }

            if ($amountMinor > $locked->totalMinor()) {
                throw ValidationException::withMessages(['card_amount' => __('pos.errors.card_amount_above_total')]);
            }

            $now = now();

            $locked->update([
                'frozen_at' => $locked->frozen_at ?? $now,
                'terminal_payment_terminal_id' => $terminal->id,
                'terminal_payment_amount' => Money::toDecimal($amountMinor),
                'terminal_payment_started_by' => $actor->id,
                'terminal_payment_started_at' => $now,
            ]);

            return $locked;
        }, FinalizeCheckout::DEADLOCK_ATTEMPTS);
    }
}
