<?php

declare(strict_types=1);

namespace App\Actions\Storefront;

use App\Enums\Currency;
use App\Enums\OrderStatus;
use App\Enums\StorefrontChannel;
use App\Enums\StorefrontOrderStatus;
use App\Events\OrderCreated;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\StorefrontOrder;
use App\Models\StorefrontOrderLine;
use App\Models\User;
use App\Services\CartService;
use App\Services\Idempotency;
use App\Services\Purchasing\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Turn the buyer's stored cart of linked branch stock into a pending order at one branch.
 *
 * The buyer must be signed in and allowed to order for the shop, and every product must be
 * linked and published to a channel the buyer may see; this is checked on the server with the
 * product rows locked, as linking locks them. A cart mixing linked and legacy products is
 * refused and kept. Prices are the branch's current TRY prices from the quote, and the order is
 * refused when they no longer match the total the buyer saw. The order holds no stock; the
 * branch confirms it. A repeated submit with the same key returns the same order.
 */
final class SubmitBranchOrder
{
    public function __construct(private readonly CartService $carts, private readonly QuoteBranchOrder $quote) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(User $buyer, int $shopId, int $storeId, int $expectedTotalMinor, string $submissionKey, ?string $notes): StorefrontOrder
    {
        if (! BranchOrderHolds::enabled()) {
            throw ValidationException::withMessages(['cart' => __('main.storefront_display_only')]);
        }

        if (! Str::isUuid($submissionKey)) {
            throw ValidationException::withMessages(['cart' => __('main.branch_orders.errors.review_again')]);
        }

        $key = Idempotency::fingerprint(['operation' => 'submit_branch_order', 'buyer' => $buyer->id, 'key' => $submissionKey]);
        $existing = StorefrontOrder::query()->where('buyer_id', $buyer->id)->where('submission_key', $key)->first();

        if ($existing !== null) {
            return $existing;
        }

        if (! Shop::visibleTo($buyer)->whereKey($shopId)->exists()) {
            throw ValidationException::withMessages(['selectedShopId' => __('main.branch_orders.errors.shop_not_allowed')]);
        }

        $notes = Str::trim((string) $notes);
        $channels = StorefrontChannel::audienceOf($buyer);

        try {
            $order = DB::transaction(fn (): StorefrontOrder => $this->submit($buyer, $shopId, $storeId, $expectedTotalMinor, $key, $notes === '' ? null : Str::limit($notes, 1000, ''), $channels));
        } catch (UniqueConstraintViolationException $exception) {
            return StorefrontOrder::query()->where('buyer_id', $buyer->id)->where('submission_key', $key)->first() ?? throw $exception;
        }

        OrderCreated::dispatch($order->order, $buyer->id);

        return $order;
    }

    /**
     * @param  list<StorefrontChannel>  $channels
     *
     * @throws ValidationException
     */
    private function submit(User $buyer, int $shopId, int $storeId, int $expectedTotalMinor, string $key, ?string $notes, array $channels): StorefrontOrder
    {
        $cart = $this->carts->getOrCreateCart($buyer->id, null);
        $items = $cart->items()->get();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['cart' => __('main.branch_orders.errors.cart_empty')]);
        }

        $quantities = $items->mapWithKeys(fn ($item): array => [(int) $item->product_id => (int) $item->quantity]);
        $productIds = $quantities->keys()->sort()->values();

        Product::query()->whereKey($productIds)->orderBy('id')->lockForUpdate()->get(['id']);

        $kind = QuoteBranchOrder::kind($quantities);

        if ($kind === QuoteBranchOrder::MIXED) {
            throw ValidationException::withMessages(['cart' => __('main.branch_orders.errors.mixed_cart')]);
        }

        $orderable = Product::query()->whereKey($productIds)->visibleInChannels($channels)->whereHas('storefrontListing')->count();

        if ($kind !== QuoteBranchOrder::BRANCH || $orderable !== $productIds->count()) {
            throw ValidationException::withMessages(['cart' => __('main.branch_orders.errors.not_orderable')]);
        }

        $branch = collect($this->quote->branches($quantities))->firstWhere('store_id', $storeId);

        if ($branch === null) {
            throw ValidationException::withMessages(['selectedStoreId' => __('main.branch_orders.errors.branch_unavailable')]);
        }

        if ($branch['total_minor'] !== $expectedTotalMinor) {
            throw ValidationException::withMessages(['selectedStoreId' => __('main.branch_orders.errors.prices_changed')]);
        }

        $order = Order::query()->create([
            'shop_id' => $shopId,
            'user_id' => $buyer->id,
            'status' => OrderStatus::PENDING,
            'total_price' => Money::toDecimal($branch['total_minor']),
            'notes' => $notes,
        ]);

        $storefrontOrder = StorefrontOrder::query()->create([
            'order_id' => $order->id,
            'store_id' => $storeId,
            'buyer_id' => $buyer->id,
            'shop_id' => $shopId,
            'submission_key' => $key,
            'status' => StorefrontOrderStatus::PENDING,
            'currency' => Currency::TRY->value,
            'total' => Money::toDecimal($branch['total_minor']),
            'notes' => $notes,
        ]);

        foreach ($branch['lines'] as $line) {
            $pieces = $line['tracking_mode']->tracksIndividualItems()
                ? array_map(fn (int $price): array => [1, $price], $line['unit_prices'])
                : [[$line['quantity'], $line['unit_prices'][0]]];

            foreach ($pieces as [$quantity, $price]) {
                $item = $order->items()->create([
                    'product_id' => $line['product_id'],
                    'quantity' => $quantity,
                    'price' => Money::toDecimal($price),
                    'subtotal' => Money::toDecimal(Money::multiply($quantity, $price)),
                ]);

                StorefrontOrderLine::query()->create([
                    'storefront_order_id' => $storefrontOrder->id,
                    'order_item_id' => $item->id,
                    'store_id' => $storeId,
                    'product_id' => $line['product_id'],
                    'product_definition_id' => $line['definition_id'],
                    'tracking_mode' => $line['tracking_mode'],
                    'quantity' => $quantity,
                    'unit_price' => Money::toDecimal($price),
                ]);
            }
        }

        $cart->items()->delete();

        BranchOrderHolds::audit($storefrontOrder, $buyer, 'storefront_order_submitted', "submitted for pickup at store {$storeId}", ['total' => $storefrontOrder->total]);

        return $storefrontOrder->setRelation('order', $order);
    }
}
