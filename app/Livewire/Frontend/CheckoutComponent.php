<?php

namespace App\Livewire\Frontend;

use App\Models\Shop;
use App\Models\Order;
use App\Enums\OrderStatus;
use App\Services\CartService;
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

    protected $rules = [
        'selectedShopId' => 'required|exists:shops,id',
        'orderNotes' => 'nullable|string|max:1000',
    ];

    protected $messages = [
        'selectedShopId.required' => 'Please select a shop.',
        'selectedShopId.exists' => 'The selected shop is invalid.',
        'orderNotes.max' => 'Order notes cannot exceed 1000 characters.',
    ];

    public function mount()
    {
        // Get user's shops on component mount
        $this->loadUserShops();
    }

    public function loadUserShops()
    {
        // This will be used in the view to populate the dropdown
        // The actual query is done in the render method
    }

    public function placeOrder()
    {
        $this->validate();

        if (!$this->hasItemsInCart()) {
            $this->addError('cart', 'Your cart is empty. Please add items before placing an order.');
            return;
        }

        $this->isProcessing = true;

        try {
            DB::transaction(function () {
                $cartService = app(CartService::class);
                $cart = $cartService->getOrCreateCart(Auth::id());

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
                $cartService->clearCart(Auth::id(), null);

                // Reset form
                $this->reset(['selectedShopId', 'orderNotes']);

                // Redirect to main page after successful order
                $this->redirect(route('main'));
            });

            session()->flash('success', 'Order placed successfully!');

        } catch (\Exception $e) {
            $this->addError('order', 'Failed to place order. Please try again. Error: ' . $e->getMessage());
        } finally {
            $this->isProcessing = false;
        }
    }

    private function hasItemsInCart(): bool
    {
        $cartService = app(CartService::class);
        $cart = $cartService->getOrCreateCart(Auth::id());
        return $cart->items->isNotEmpty();
    }

    #[Layout('layouts.frontend')]
    #[Title('Checkout')]
    public function render()
    {
        $shops = Shop::all();
        $cartService = app(CartService::class);
        $cart = $cartService->getOrCreateCart(Auth::id());

        return view('livewire.frontend.checkout-component', [
            'shops' => $shops,
            'cart' => $cart,
            'cartTotal' => $cartService->getCartTotal(Auth::id(), null),
        ]);
    }
}
