<?php

declare(strict_types=1);

namespace App\Actions\Storefront;

use App\Models\OrderItem;
use App\Models\StorefrontOrder;
use App\Models\StorefrontOrderLine;
use App\Models\User;
use App\Services\Purchasing\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The buyer accepts the prices the branch proposed for their pending order. The proposed prices
 * become the order's prices, and the branch may then confirm. Accepting twice changes nothing.
 */
final class AcceptBranchOrderPrice
{
    /**
     * @throws ValidationException
     */
    public function __invoke(User $buyer, int $storefrontOrderId): StorefrontOrder
    {
        $order = StorefrontOrder::query()->where('buyer_id', $buyer->id)->findOrFail($storefrontOrderId);

        return DB::transaction(function () use ($buyer, $order): StorefrontOrder {
            $order = StorefrontOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->isPending() && $order->price_change_requested_at === null) {
                return $order;
            }

            if (! $order->awaitsPriceAcceptance()) {
                throw ValidationException::withMessages(['order' => __('main.branch_orders.errors.not_pending')]);
            }

            $lines = StorefrontOrderLine::query()->where('storefront_order_id', $order->id)->orderBy('id')->lockForUpdate()->get();
            $total = 0;

            foreach ($lines as $line) {
                $price = $line->proposedUnitPriceMinor() ?? $line->unitPriceMinor();
                $subtotal = Money::multiply($line->quantity, $price);
                $total += $subtotal;

                $line->update(['unit_price' => Money::toDecimal($price), 'proposed_unit_price' => null]);
                OrderItem::query()->whereKey($line->order_item_id)->update(['price' => Money::toDecimal($price), 'subtotal' => Money::toDecimal($subtotal)]);
            }

            $order->update(['total' => Money::toDecimal($total), 'price_change_requested_at' => null, 'price_change_requested_by' => null]);
            $order->order()->update(['total_price' => Money::toDecimal($total)]);

            BranchOrderHolds::audit($order, $buyer, 'storefront_order_price_accepted', 'buyer accepted the proposed prices', ['total' => $order->total]);

            return $order;
        });
    }
}
