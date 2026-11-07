<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;

class SalePolicy
{
    /**
     * Counter users see their own sales; the owner sees every sale in stores they can access.
     */
    public function view(User $user, Sale $sale): bool
    {
        $store = $sale->store;

        if (! $user->can('usePos', $store)) {
            return false;
        }

        return (int) $sale->user_id === (int) $user->id || $user->can('viewAllSales', $store);
    }

    public function update(User $user, Sale $sale): bool
    {
        return false;
    }

    public function delete(User $user, Sale $sale): bool
    {
        return false;
    }
}
