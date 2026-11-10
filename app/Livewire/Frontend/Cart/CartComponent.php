<?php

declare(strict_types=1);

namespace App\Livewire\Frontend\Cart;

use App\Actions\Storefront\StorefrontOrderGuard;
use App\Enums\OrderStatus;
use App\Events\OrderCreated;
use App\Facades\Cart as CartFacade;
use App\Models\Cart;
use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * @property-read Cart $cart
 * @property-read float $total
 */
class CartComponent extends Component
{
    /** Session key holding the revision of the empty cart a buyer signed in to. */
    public const ADOPTION_GRANT = 'cart.adoption_grant';

    public Cart $cart;

    /**
     * Mount the component.
     */
    public function mount(): void {}

    /**
     * @param  string|null  $revision  The stored cart revision this tab last loaded; a stale tab is refused.
     */
    #[On('sync-cart')]
    public function handleSyncCart(array $items, ?string $revision = null): void
    {
        Log::info('Cart sync requested', ['items_count' => count($items)]);

        $user_id = auth()->id();

        // If the user is not authenticated, we cannot sync the cart.

        try {
            if (! $user_id) {
                Log::info('User is not authenticated, skipping cart sync');

                return;
            } else {
                $result = CartFacade::syncBrowserCart($user_id, $items, $revision, session()->pull(self::ADOPTION_GRANT));
                $this->reportCart($result, self::quantities($items) !== self::quantities($result['items'] ?? $items));

                Log::info('Cart synced successfully', ['user_id' => $user_id]);
            }
        } catch (\Exception $e) {
            Log::error('Error syncing cart', [
                'user_id' => $user_id,
                'error' => $e->getMessage(),
            ]);
        }

    }

    #[On('clear-cart')]
    public function handleClearCart(?string $revision = null): void
    {
        $user_id = auth()->id();
        try {
            if (! $user_id) {
                Log::info('User is not authenticated, skipping cart clear User ID: '.$user_id);

                return;
            } else {
                $result = CartFacade::clearBrowserCart($user_id, $revision);
                $this->reportCart($result, $result['items'] !== null && $result['items'] !== []);
            }
        } catch (\Exception $e) {
            Log::error('Error Clearing cart', [
                'user_id' => $user_id,
                'error' => $e->getMessage(),
            ]);
        }

    }

    #[On('remove-item')]
    public function handleRemoveItem($id, ?string $revision = null): void
    {
        $user_id = auth()->id();
        try {
            if (! $user_id) {
                Log::info('User is not authenticated, skipping Removing : '.$user_id);

                return;
            } else {
                $result = CartFacade::removeFromBrowserCart($user_id, (int) $id, $revision);
                $this->reportCart($result, array_key_exists((int) $id, self::quantities($result['items'] ?? [])));
            }
        } catch (\Exception $e) {
            Log::error('Error Clearing cart', [
                'user_id' => $user_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    #[On('order-now')]
    public function handleOrderNow(array $items, int $subtotal): void
    {
        Log::info('Order Now', ['items' => $items]);

        try {
            StorefrontOrderGuard::assertOrderable(array_column($items, 'product_id'), 'order');
        } catch (ValidationException $exception) {
            $this->addError('order', $exception->getMessage());
            $this->dispatch('notify', [['type' => 'alert-danger', 'message' => $exception->getMessage(), 'sec' => 5000]]);

            return;
        }

        try {
            DB::transaction(function () use ($items, $subtotal) {
                Log::info('Starting order Transaction');

                StorefrontOrderGuard::lockOrderable(array_column($items, 'product_id'), 'order');

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
                CartFacade::clearCart(Auth::id(), null);

                // Dispatch OrderCreated event
                OrderCreated::dispatch($order, Auth::id());
            });

            $this->dispatch('notify', [
                'type' => 'alert-success',
                'message' => 'Order placed successfully!',
            ]);

        } catch (ValidationException $exception) {
            $this->addError('order', $exception->getMessage());
            $this->dispatch('notify', [['type' => 'alert-danger', 'message' => $exception->getMessage(), 'sec' => 5000]]);
        } catch (\Exception $e) {
            $this->addError('order', 'Failed to place order. Please try again. Error: '.$e->getMessage());
        } finally {

        }
    }

    /**
     * Send the browser the stored cart's revision, with its items when the browser must reload
     * them, and tell the buyer when a refused change would have altered the stored cart.
     *
     * @param  array{accepted: bool, revision: string, items: list<array<string, mixed>>|null}  $result
     * @param  bool  $changeLost  Whether the stored cart differs from what the browser asked for.
     */
    private function reportCart(array $result, bool $changeLost): void
    {
        $this->dispatch('cart-items-from-server', items: $result['items'], revision: $result['revision']);

        if (! $result['accepted'] && $changeLost) {
            $this->dispatch('notify', [['type' => 'alert-warning', 'message' => __('cart.updated_elsewhere'), 'sec' => 5000]]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, int> Quantity by product id.
     */
    private static function quantities(array $items): array
    {
        $quantities = [];

        foreach ($items as $item) {
            $quantities[(int) $item['product_id']] = (int) $item['quantity'];
        }

        ksort($quantities);

        return $quantities;
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
