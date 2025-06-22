<?php

namespace App\Livewire\Frontend;

use App\Models\Shop;
use App\Models\Order;
use App\Enums\OrderStatus;
use App\Facades\Cart;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class CheckoutComponent extends Component
{
    public ?int $selectedShopId = null;
    public string $orderNotes = '';
    public bool $isProcessing = false;

    protected array $rules = [
        'selectedShopId' => 'required|exists:shops,id',
        'orderNotes' => 'nullable|string|max:1000',
    ];

    protected array $messages = [
        'selectedShopId.required' => 'Please select a shop.',
        'selectedShopId.exists' => 'The selected shop is invalid.',
        'orderNotes.max' => 'Order notes cannot exceed 1000 characters.',
    ];

    public function mount()
    {
        // Remove cart initialization from mount
    }

    // Use computed properties instead of private properties
    public function getCartProperty()
    {
        return Cart::getOrCreateCart(Auth::id(), null);
    }

    public function getCartTotalProperty()
    {
        return Cart::getCartTotal(Auth::id(), null);
    }

    public function placeOrder(): void
    {
        Log::info('Starting order placement');
        $this->validate();

        Log::info('Order Validation passed');

        if (!$this->hasItemsInCart()) {
            $this->addError('cart', 'Your cart is empty. Please add items before placing an order.');
            return;
        }

        $this->isProcessing = true;

        try {
            DB::transaction(function () {
                Log::info('Starting order Transaction');

                $cart = $this->cart; // Get fresh cart data

                if ($cart->items->isEmpty()) {
                    throw new \Exception('Cart is empty');
                }

                // Calculate total price
                $totalPrice = $cart->items->sum('subtotal');

                // Create the order
                $order = Order::create([
                    'shop_id' => $this->selectedShopId,
                    'user_id' => Auth::id(),
                    'status' => OrderStatus::PENDING,
                    'total_price' => $totalPrice,
                    'notes' => $this->orderNotes,
                ]);

                // Create order items from cart items
                foreach ($cart->items as $cartItem) {
                    $order->items()->create([
                        'product_id' => $cartItem->product_id,
                        'quantity' => $cartItem->quantity,
                        'price' => $cartItem->price,
                        'subtotal' => $cartItem->subtotal,
                    ]);
                }

                // Clear the cart after successful order creation
                Cart::clearCart(Auth::id(), null);
            });

            // Reset form after successful transaction
            $this->reset(['selectedShopId', 'orderNotes']);

            // Dispatch event to clear localStorage cart
            $this->dispatch('clear-cart');

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Order placed successfully!'
            ]);

            // Redirect to main page after successful order
            $this->redirect(route('main'));

        } catch (\Exception $e) {
            $this->addError('order', 'Failed to place order. Please try again. Error: ' . $e->getMessage());
        } finally {
            $this->isProcessing = false;
        }
    }

    private function hasItemsInCart(): bool
    {
        return $this->cart->items->isNotEmpty();
    }

    #[Layout('layouts.frontend')]
    #[Title('Checkout')]
    public function render()
    {
        $shops = Shop::visibleTo(auth()->user())->get();

        return view('livewire.frontend.checkout-component', [
            'shops' => $shops,
            'cart' => $this->cart,
            'cartTotal' => $this->cartTotal,
        ]);
    }
}
