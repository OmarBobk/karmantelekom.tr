<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Models\Store;
use Illuminate\Validation\ValidationException;

/**
 * The branch's initial-setup boundary for opening stock.
 */
final class InventorySetup
{
    /**
     * Confirm the branch is still in initial setup, reading its store row in share mode.
     *
     * Call inside the ledger mutation, after the definition lock. CompleteInventorySetup locks
     * the same row for update, so a completion either commits first and is seen here, or waits
     * for this opening entry to commit.
     *
     * @throws ValidationException
     */
    public static function assertOpen(Store $store): void
    {
        $completedAt = Store::query()->whereKey($store->id)->sharedLock()->value('inventory_setup_completed_at');

        if ($completedAt !== null) {
            throw ValidationException::withMessages(['setup' => __('inventory.errors.setup_completed')]);
        }
    }
}
