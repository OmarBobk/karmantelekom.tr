<?php

declare(strict_types=1);

namespace App\Actions\Storefront;

use App\Enums\UnitStatus;
use App\Models\InventoryUnit;
use App\Models\Store;
use App\Models\StorefrontOrder;
use App\Models\StorefrontOrderLine;
use App\Models\User;
use App\Services\Purchasing\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Ask the buyer to accept the branch's current prices in the order's channel for a pending order
 * whose agreed prices no longer hold, for the devices the branch intends to hand over. Each
 * proposal gets a new version and a fingerprint of its line prices and devices; the buyer
 * accepts that exact proposal, and a newer one replaces it. Nothing is held; the branch confirms
 * after the buyer accepts, and the buyer may cancel instead.
 */
final class RequestBranchOrderPriceChange
{
    /**
     * @param  array<int|string, mixed>  $deviceChoices  Intended device id by order line id, for device lines.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $actor, Store $store, int $storefrontOrderId, array $deviceChoices): StorefrontOrder
    {
        Gate::forUser($actor)->authorize('manageStorefrontOrders', $store);

        $order = StorefrontOrder::query()->where('store_id', $store->id)->findOrFail($storefrontOrderId);

        return DB::transaction(function () use ($actor, $store, $order, $deviceChoices): StorefrontOrder {
            $order = StorefrontOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (! $order->isPending()) {
                throw ValidationException::withMessages(['order' => __('storefront.orders.errors.not_pending', ['status' => $order->displayStatus()->label()])]);
            }

            $lines = StorefrontOrderLine::query()->where('storefront_order_id', $order->id)->with('productDefinition')->orderBy('id')->lockForUpdate()->get();
            $proposals = [];

            foreach ($lines as $line) {
                $unit = null;

                if ($line->tracksUnits()) {
                    $unitId = filter_var($deviceChoices[$line->id] ?? null, FILTER_VALIDATE_INT);
                    $unit = $unitId === false ? null : InventoryUnit::query()->whereKey($unitId)->where('store_id', $store->id)
                        ->where('product_definition_id', $line->product_definition_id)->where('status', UnitStatus::AVAILABLE)->first();

                    if ($unit === null) {
                        throw ValidationException::withMessages(['devices' => __('storefront.orders.errors.choose_devices')]);
                    }
                }

                $price = StorefrontPricing::itemPriceMinor($line->product_id, $line->productDefinition, $unit, $order->channel, (int) $lines->where('product_id', $line->product_id)->sum('quantity'));

                if ($price === null) {
                    throw ValidationException::withMessages(["lines.{$line->id}" => __('storefront.orders.errors.price_unavailable')]);
                }

                $proposals[$line->id] = [$price, $unit?->id];
            }

            if (collect($lines)->every(fn (StorefrontOrderLine $line): bool => $proposals[$line->id][0] === $line->unitPriceMinor())) {
                throw ValidationException::withMessages(['order' => __('storefront.orders.errors.prices_unchanged')]);
            }

            foreach ($lines as $line) {
                $line->update(['proposed_unit_price' => Money::toDecimal($proposals[$line->id][0]), 'proposed_unit_id' => $proposals[$line->id][1]]);
            }

            $version = $order->price_proposal_version + 1;

            $order->update([
                'price_change_requested_by' => $actor->id,
                'price_change_requested_at' => now(),
                'price_proposal_version' => $version,
                'price_proposal_fingerprint' => StorefrontOrder::proposalFingerprint($order->id, $version, $lines),
            ]);

            BranchOrderHolds::audit($order, $actor, 'storefront_order_price_change_requested', "price proposal {$version} by {$store->code}", ['version' => $version]);

            return $order;
        });
    }
}
