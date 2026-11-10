<?php

declare(strict_types=1);

namespace App\Actions\Storefront;

use App\Enums\StorefrontOrderStatus;
use App\Models\StorefrontOrder;
use App\Models\StorefrontOrderLine;
use Illuminate\Support\Facades\DB;

/**
 * Store confirmed orders past their pickup deadline as expired and clear their holds. The holds
 * already stopped counting at the deadline; this records it and updates the order history. An
 * order whose counter payment started keeps its holds until that payment is resolved.
 */
final class ExpireBranchOrders
{
    /**
     * @return int The number of orders expired.
     */
    public function __invoke(): int
    {
        $expired = 0;

        $candidates = StorefrontOrder::query()
            ->where('status', StorefrontOrderStatus::CONFIRMED)
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->pluck('id');

        foreach ($candidates as $orderId) {
            $expired += DB::transaction(function () use ($orderId): int {
                $order = StorefrontOrder::query()->whereKey($orderId)->lockForUpdate()->first();

                if ($order === null || ! $order->isPastPickup()) {
                    return 0;
                }

                $lines = StorefrontOrderLine::query()->where('storefront_order_id', $order->id)->orderBy('id')->lockForUpdate()->get();

                if ($lines->contains(fn (StorefrontOrderLine $line): bool => $line->claimed_attempt_id !== null)) {
                    return 0;
                }

                StorefrontOrderLine::query()->where('storefront_order_id', $order->id)->update(['held_quantity' => 0, 'active_unit_id' => null]);

                $order->update(['status' => StorefrontOrderStatus::EXPIRED, 'expired_at' => $order->expires_at]);
                $order->order()->update(['status' => StorefrontOrderStatus::EXPIRED->orderStatus()]);

                activity('storefront')
                    ->performedOn($order)
                    ->event('storefront_order_expired')
                    ->withProperties(['store_id' => $order->store_id, 'order_id' => $order->order_id, 'status' => $order->status->value])
                    ->log("Storefront order #{$order->order_id}: pickup window ended");

                return 1;
            });
        }

        return $expired;
    }
}
