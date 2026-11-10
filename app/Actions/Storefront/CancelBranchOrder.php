<?php

declare(strict_types=1);

namespace App\Actions\Storefront;

use App\Enums\StorefrontOrderStatus;
use App\Models\Store;
use App\Models\StorefrontOrder;
use App\Models\StorefrontOrderLine;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Cancel a storefront order and release its holds.
 *
 * The buyer may cancel while the order is pending. The fulfilling branch, or the owner through
 * it, may cancel a pending or confirmed order with a reason. A counter checkout that started
 * payment for the order keeps its holds: cancellation is refused until that payment is resolved
 * through the counter's recovery flow. Cancelling a cancelled order changes nothing.
 */
final class CancelBranchOrder
{
    /**
     * @throws ValidationException
     */
    public function byBuyer(User $buyer, int $storefrontOrderId): StorefrontOrder
    {
        $order = StorefrontOrder::query()->where('buyer_id', $buyer->id)->findOrFail($storefrontOrderId);

        return $this->cancel($buyer, $order->id, null, buyer: true);
    }

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function byBranch(User $actor, Store $store, int $storefrontOrderId, ?string $reason): StorefrontOrder
    {
        Gate::forUser($actor)->authorize('manageStorefrontOrders', $store);

        $order = StorefrontOrder::query()->where('store_id', $store->id)->findOrFail($storefrontOrderId);
        $reason = Str::trim((string) $reason);

        if ($reason === '' || mb_strlen($reason) > StorefrontOrder::MAX_NOTE_LENGTH) {
            throw ValidationException::withMessages(['reason' => __('storefront.orders.errors.reason_required', ['max' => StorefrontOrder::MAX_NOTE_LENGTH])]);
        }

        return $this->cancel($actor, $order->id, $reason, buyer: false);
    }

    /**
     * @throws ValidationException
     */
    private function cancel(User $actor, int $orderId, ?string $reason, bool $buyer): StorefrontOrder
    {
        return DB::transaction(function () use ($actor, $orderId, $reason, $buyer): StorefrontOrder {
            $order = StorefrontOrder::query()->whereKey($orderId)->lockForUpdate()->firstOrFail();

            if ($order->status === StorefrontOrderStatus::CANCELLED) {
                return $order;
            }

            $allowed = $buyer ? $order->isPending() : ($order->isPending() || $order->isConfirmed());

            if (! $allowed) {
                $key = $buyer ? 'main.branch_orders.errors.cancel_not_allowed' : 'storefront.orders.errors.not_cancellable';

                throw ValidationException::withMessages(['order' => __($key, ['status' => $order->displayStatus()->label()])]);
            }

            $lines = StorefrontOrderLine::query()->where('storefront_order_id', $order->id)->orderBy('id')->lockForUpdate()->get();

            if ($lines->contains(fn (StorefrontOrderLine $line): bool => $line->claimed_attempt_id !== null)) {
                throw ValidationException::withMessages(['order' => __('storefront.orders.errors.payment_in_progress')]);
            }

            StorefrontOrderLine::query()->where('storefront_order_id', $order->id)->update(['held_quantity' => 0, 'active_unit_id' => null]);

            $order->update([
                'status' => StorefrontOrderStatus::CANCELLED,
                'cancelled_by' => $actor->id,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
                'price_change_requested_at' => null,
            ]);
            $order->order()->update(['status' => StorefrontOrderStatus::CANCELLED->orderStatus()]);

            BranchOrderHolds::audit($order, $actor, 'storefront_order_cancelled', $buyer ? 'cancelled by the buyer' : 'cancelled by the branch', ['reason' => $reason]);

            return $order;
        });
    }
}
