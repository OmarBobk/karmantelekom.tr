<?php

declare(strict_types=1);

namespace App\Livewire\Frontend\Cart;

use App\Enums\OrderStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Facades\Cart as CartFacade;
use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Attributes\Computed;

/**
 * @property-read Cart $cart
 * @property-read float $total
 */
class CartComponent extends Component
{
    public Cart $cart;

    /**
     * Mount the component.
     */
    public function mount(): void
    {

    }

    #[On('sync-cart')]
    public function handleSyncCart(array $items): void
    {
        Log::info('Cart sync requested', ['items_count' => count($items)]);

        $user_id = auth()->id();

        // If the user is not authenticated, we cannot sync the cart.

        try {
            if (!$user_id) {
                Log::info('User is not authenticated, skipping cart sync');
                return;
            } else {
                $cart = CartFacade::syncCart($user_id, $items);
                if (is_array($cart) && !empty($cart)) {
                    $this->dispatch('cart-items-from-server', $cart);
                }

                Log::info('Cart synced successfully', ['user_id' => $user_id]);
            }
        } catch (\Exception $e) {
            Log::error('Error syncing cart', [
                'user_id' => $user_id,
                'error' => $e->getMessage()
            ]);
        }

    }

    #[On('clear-cart')]
    public function handleClearCart(): void
    {
        $user_id = auth()->id();
        try {
            if (!$user_id) {
                Log::info('User is not authenticated, skipping cart clear User ID: ' . $user_id);
                return;
            } else {
                CartFacade::clearCart($user_id, null);
            }
        } catch (\Exception $e) {
            Log::error('Error Clearing cart', [
                'user_id' => $user_id,
                'error' => $e->getMessage()
            ]);
        }

    }

    #[On('remove-item')]
    public function handleRemoveItem($id): void
    {
        $user_id = auth()->id();
        try {
            if (!$user_id) {
                Log::info('User is not authenticated, skipping Removing : ' . $user_id);
                return;
            } else {
                CartFacade::removeFromCart($user_id, null, $id);
            }
        } catch (\Exception $e) {
            Log::error('Error Clearing cart', [
                'user_id' => $user_id,
                'error' => $e->getMessage()
            ]);
        }
    }

    #[On('order-now')]
    public function handleOrderNow(array $items, int $subtotal): void
    {
        Log::info('Order Now', ['items' => $items]);

        try {
            DB::transaction(function () use ($items, $subtotal) {
                Log::info('Starting order Transaction');

                $cartItems = collect($items); // Get fresh cart data

                // Calculate total price
                $totalPrice = $subtotal;

                // Create the order
                $order = Order::create([
                    'shop_id' => auth()->user()->ownedShop->id,
                    'user_id' => Auth::id(),
                    'status' => OrderStatus::PENDING,
                    'total_price' => $totalPrice,
                    'notes' => '',
                ]);

                // Create order items from cart items
                foreach ($cartItems as $cartItem) {
                    $order->items()->create([
                        'product_id' => $cartItem['product_id'],
                        'quantity' => $cartItem['quantity'],
                        'price' => $cartItem['price'],
                        'subtotal' => $cartItem['subtotal'],
                    ]);
                }

                Log::info('Order', ['order' => $order]);
                Log::info('Order items', ['items' => $order->items]);

                // Clear the cart after successful order creation
                \App\Facades\Cart::clearCart(Auth::id(), null);

                // Dispatch OrderCreated event
                \App\Events\OrderCreated::dispatch($order, Auth::id());
            });



            $this->dispatch('notify', [
                'type' => 'alert-success',
                'message' => 'Order placed successfully!'
            ]);

        } catch (\Exception $e) {
            $this->addError('order', 'Failed to place order. Please try again. Error: ' . $e->getMessage());
        } finally {

        }
    }

    /**
     * Render the component.
     */
    #[Layout('layouts.frontend')]
    public function render(): View
    {
        return view('livewire.frontend.cart.cart-component');
    }
}
