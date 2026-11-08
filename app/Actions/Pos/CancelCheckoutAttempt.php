<?php

declare(strict_types=1);

namespace App\Actions\Pos;

use App\Enums\CheckoutAttemptStatus;
use App\Models\CheckoutAttempt;
use App\Models\Store;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CancelCheckoutAttempt
{
    /**
     * Cancel an open attempt on which no payment was recorded.
     *
     * An attempt with a started terminal payment, a card approval, or a pending cash intake cannot
     * be cancelled: it stays open until the sale is completed or the owner records the resolution.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $actor, Store $store, string $attemptKey): CheckoutAttempt
    {
        Gate::forUser($actor)->authorize('usePos', $store);

        $attempt = ReviewCheckout::findAttempt($store, $attemptKey);

        if (! Gate::forUser($actor)->any(['operate', 'resolve'], $attempt)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($actor, $attempt): CheckoutAttempt {
            $locked = CheckoutAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === CheckoutAttemptStatus::CANCELLED) {
                return $locked;
            }

            ReviewCheckout::assertOpen($locked);

            if ($locked->cardApproval()->exists()) {
                throw ValidationException::withMessages(['attempt' => __('pos.errors.cancel_with_approval')]);
            }

            if ($locked->hasStartedTerminalPayment()) {
                throw ValidationException::withMessages(['attempt' => __('pos.errors.cancel_with_terminal_payment')]);
            }

            if ($locked->hasPendingCashIntake()) {
                throw ValidationException::withMessages(['attempt' => __('pos.cashbox.errors.cancel_with_cash_intake')]);
            }

            $locked->update([
                'status' => CheckoutAttemptStatus::CANCELLED,
                'cancelled_at' => now(),
                'resolved_by' => $actor->id,
                'resolved_at' => now(),
            ]);

            return $locked;
        });
    }
}
