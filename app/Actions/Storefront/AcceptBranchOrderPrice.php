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
 * The buyer accepts the exact price proposal their page showed, identified by its fingerprint.
 * Under the order lock the proposal must still be the pending one and still match its lines;
 * a stale fingerprint is refused, and the page then shows the current proposal. Accepting the
 * proposal that was already accepted changes nothing and never accepts a newer one. The
 * proposed prices become the order's prices and the proposed devices stay its devices, and the
 * branch may then confirm.
 */
final class AcceptBranchOrderPrice
{
    /**
     * @throws ValidationException
     */
    public function __invoke(User $buyer, int $storefrontOrderId, string $proposal): StorefrontOrder
    {
        $order = StorefrontOrder::query()->where('buyer_id', $buyer->id)->findOrFail($storefrontOrderId);

        return DB::transaction(function () use ($buyer, $order, $proposal): StorefrontOrder {
            $order = StorefrontOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($proposal !== '' && $order->accepted_price_proposal !== null && hash_equals($order->accepted_price_proposal, $proposal)) {
                return $order;
            }

            if (! $order->awaitsPriceAcceptance()) {
                throw ValidationException::withMessages(['order' => __('main.branch_orders.errors.not_pending')]);
            }

            $lines = StorefrontOrderLine::query()->where('storefront_order_id', $order->id)->orderBy('id')->lockForUpdate()->get();
            $current = StorefrontOrder::proposalFingerprint($order->id, $order->price_proposal_version, $lines);

            if (! hash_equals((string) $order->price_proposal_fingerprint, $proposal) || ! hash_equals($current, $proposal)) {
                throw ValidationException::withMessages(['order' => __('main.branch_orders.errors.proposal_changed')]);
            }

            $total = 0;

            foreach ($lines as $line) {
                $price = $line->proposedUnitPriceMinor() ?? $line->unitPriceMinor();
                $subtotal = Money::multiply($line->quantity, $price);
                $total += $subtotal;

                $line->update(['unit_price' => Money::toDecimal($price), 'proposed_unit_price' => null]);
                OrderItem::query()->whereKey($line->order_item_id)->update(['price' => Money::toDecimal($price), 'subtotal' => Money::toDecimal($subtotal)]);
            }

            $order->update([
                'total' => Money::toDecimal($total),
                'accepted_price_proposal' => $proposal,
                'price_proposal_fingerprint' => null,
                'price_change_requested_at' => null,
                'price_change_requested_by' => null,
            ]);
            $order->order()->update(['total_price' => Money::toDecimal($total)]);

            BranchOrderHolds::audit($order, $buyer, 'storefront_order_price_accepted', "buyer accepted price proposal {$order->price_proposal_version}", ['total' => $order->total, 'version' => $order->price_proposal_version]);

            return $order;
        });
    }
}
