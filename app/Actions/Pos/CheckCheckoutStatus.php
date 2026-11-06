<?php

declare(strict_types=1);

namespace App\Actions\Pos;

use App\Models\CheckoutAttempt;
use App\Models\Store;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CheckCheckoutStatus
{
    /**
     * Read an attempt's definitive state after an uncertain result.
     *
     * The attempt row is locked, so this waits for an in-flight finalization to commit or roll
     * back instead of reporting a state that is about to change. A missing sale on an open
     * attempt is never a reason to take payment again: the card approval stays on the attempt.
     *
     * @throws AuthorizationException
     */
    public function __invoke(User $actor, Store $store, string $attemptKey): CheckoutAttempt
    {
        Gate::forUser($actor)->authorize('usePos', $store);

        $attempt = ReviewCheckout::findAttempt($store, $attemptKey);
        Gate::forUser($actor)->authorize('view', $attempt);

        return DB::transaction(function () use ($attempt): CheckoutAttempt {
            $locked = CheckoutAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            $locked->load(['cardApproval.terminal', 'terminalPaymentTerminal', 'sale']);

            return $locked;
        });
    }
}
