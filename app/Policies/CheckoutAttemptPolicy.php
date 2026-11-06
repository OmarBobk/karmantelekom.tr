<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CheckoutAttempt;
use App\Models\User;

class CheckoutAttemptPolicy
{
    /**
     * The cashier who opened the attempt, or the owner, may see it while they can use the counter.
     */
    public function view(User $user, CheckoutAttempt $attempt): bool
    {
        $store = $attempt->store;

        if (! $user->can('usePos', $store)) {
            return false;
        }

        return (int) $attempt->user_id === (int) $user->id || $user->can('viewAllSales', $store);
    }

    /**
     * Only the cashier who opened the attempt reviews, tenders, or completes it.
     */
    public function operate(User $user, CheckoutAttempt $attempt): bool
    {
        return (int) $attempt->user_id === (int) $user->id && $user->can('usePos', $attempt->store);
    }

    public function resolve(User $user, CheckoutAttempt $attempt): bool
    {
        return $user->can('resolveCheckoutAttempts', $attempt->store);
    }
}
