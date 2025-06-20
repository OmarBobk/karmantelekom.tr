<?php

declare(strict_types=1);

namespace App\Livewire\Frontend\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Facades\Cart as CartFacade;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
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
        CartFacade::clearCart($user_id, null);

    }

    #[On('remove-item')]
    public function handleRemoveItem(int $product_id): void
    {

        $user_id = auth()->id();
        CartFacade::removeFromCart($user_id, null, $product_id);

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
