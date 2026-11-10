<?php

declare(strict_types=1);

namespace App\Actions\Storefront;

use App\Enums\Currency;
use App\Enums\OrderStatus;
use App\Enums\StorefrontOrderStatus;
use App\Events\OrderCreated;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\StorefrontListing;
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
 * linked and published to the order's channel; this is checked on the server with the product
 * rows locked, as linking locks them. The order is bound to that channel (StorefrontPricing). A
 * cart mixing linked and legacy products is refused and kept, and so is a cart with a product
 * that has no price in the channel. Prices are the branch's current TRY channel prices from the
 * quote, and the order is refused when they no longer match the total the buyer saw. The order
 * holds no stock; the branch confirms it. A repeated submit with the same key returns the same
 * order, even after its cart was emptied.
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
        $cart = $this->carts->getOrCreateCart($buyer->id, null);

        try {
            $order = DB::transaction(fn (): StorefrontOrder => $this->submit($buyer, $cart, $shopId, $storeId, $expectedTotalMinor, $key, $notes === '' ? null : Str::limit($notes, 1000, '')));
        } catch (UniqueConstraintViolationException $exception) {
            return StorefrontOrder::query()->where('buyer_id', $buyer->id)->where('submission_key', $key)->first() ?? throw $exception;
        }

        if (! $order->wasRecentlyCreated) {
            return $order;
        }

        OrderCreated::dispatch($order->order, $buyer->id);

        return $order;
    }

    /**
     * The cart row is locked before anything else is read, as cart changes lock it. The cart's
     * items are then read under lock, the order is made from exactly those items, and only those
     * items are deleted. A second submission of the same cart waits for the first and finds the
     * cart empty, or finds its own order when it repeats the same key.
     *
     * @throws ValidationException
     */
    private function submit(User $buyer, Cart $cart, int $shopId, int $storeId, int $expectedTotalMinor, string $key, ?string $notes): StorefrontOrder
    {
        CartService::lockCart($cart);
        $items = CartItem::query()->where('cart_id', $cart->id)->orderBy('id')->lockForUpdate()->get();
        $existing = StorefrontOrder::query()->where('buyer_id', $buyer->id)->where('submission_key', $key)->first();

        if ($existing !== null) {
            return $existing;
        }

        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['cart' => __('main.branch_orders.errors.cart_empty')]);
        }

        $quantities = $items->mapWithKeys(fn (CartItem $item): array => [(int) $item->product_id => (int) $item->quantity]);
        $productIds = $quantities->keys()->sort()->values();

        Product::query()->whereKey($productIds)->orderBy('id')->lockForUpdate()->get(['id']);
        $listings = StorefrontListing::query()->whereIn('product_id', $productIds)->sharedLock()->get()->keyBy('product_id');

        $kind = QuoteBranchOrder::kind($quantities);

        if ($kind === QuoteBranchOrder::MIXED) {
            throw ValidationException::withMessages(['cart' => __('main.branch_orders.errors.mixed_cart')]);
        }

        $channel = $kind === QuoteBranchOrder::BRANCH ? StorefrontPricing::orderChannel($buyer, $productIds, $listings) : null;

        if ($channel === null || Product::query()->whereKey($productIds)->visibleInChannels([$channel])->count() !== $productIds->count()) {
            throw ValidationException::withMessages(['cart' => __('main.branch_orders.errors.not_orderable')]);
        }

        if (QuoteBranchOrder::priceGaps($quantities, $channel) !== []) {
            throw ValidationException::withMessages(['cart' => __('main.branch_orders.errors.price_unavailable')]);
        }

        $branch = collect($this->quote->branches($quantities, $channel))->firstWhere('store_id', $storeId);

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
            'channel' => $channel,
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

        CartItem::query()->whereKey($items->modelKeys())->delete();
        CartService::advanceRevision($cart);

        BranchOrderHolds::audit($storefrontOrder, $buyer, 'storefront_order_submitted', "submitted for pickup at store {$storeId} in the {$channel->value} channel", ['total' => $storefrontOrder->total, 'channel' => $channel->value]);

        return $storefrontOrder->setRelation('order', $order);
    }
}
