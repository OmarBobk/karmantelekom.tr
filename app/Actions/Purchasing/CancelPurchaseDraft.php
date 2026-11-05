<?php

declare(strict_types=1);

namespace App\Actions\Purchasing;

use App\Enums\PurchaseStatus;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CancelPurchaseDraft
{
    /**
     * Cancel a draft that was never posted. The draft and its lines are kept for reference.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $actor, Purchase $purchase): TransitionResult
    {
        Gate::forUser($actor)->authorize('update', $purchase);

        return DB::transaction(function () use ($actor, $purchase): TransitionResult {
            $locked = Purchase::query()->whereKey($purchase->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === PurchaseStatus::CANCELLED) {
                return new TransitionResult($locked, false);
            }

            if ($locked->status !== PurchaseStatus::DRAFT) {
                throw ValidationException::withMessages(['revision' => __('purchases.errors.not_draft')]);
            }

            $locked->update([
                'status' => PurchaseStatus::CANCELLED,
                'cancelled_by' => $actor->id,
                'cancelled_at' => now(),
            ]);

            return new TransitionResult($locked, true);
        });
    }
}
