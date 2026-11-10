<?php

declare(strict_types=1);

namespace App\Actions\Storefront;

use App\Enums\OrderStatus;
use App\Events\OrderCreated;
use App\Models\Order;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PlaceCartOrder
{
    public function __construct(private readonly CartService $carts) {}

    /**
     * Turn the buyer's stored cart into a pending order for the shop, then empty the cart.
     *
     * The cart's products are locked and rechecked before any order row is written, so a product
     * linked to branch stock while checkout waited is refused and nothing is written.
     *
     * @throws ValidationException Under "cart" when the cart is empty or holds display-only stock.
     */
    public function __invoke(User $buyer, int $shopId, ?string $notes): Order
    {
        $order = DB::transaction(function () use ($buyer, $shopId, $notes): Order {
            $cart = $this->carts->getOrCreateCart($buyer->id, null);
            $items = $cart->items()->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['cart' => 'Your cart is empty. Please add items before placing an order.']);
            }

            StorefrontOrderGuard::lockOrderable($items->pluck('product_id'), 'cart');

            $order = Order::query()->create([
                'shop_id' => $shopId,
                'user_id' => $buyer->id,
                'status' => OrderStatus::PENDING,
                'total_price' => $items->sum('subtotal'),
                'notes' => $notes,
            ]);

            foreach ($items as $item) {
                $order->items()->create([
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'subtotal' => $item->subtotal,
                ]);
            }

            $cart->items()->delete();

            return $order;
        });

        OrderCreated::dispatch($order, $buyer->id);

        return $order;
    }
}
