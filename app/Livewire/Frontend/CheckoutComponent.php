<?php

namespace App\Livewire\Frontend;

use App\Models\Shop;
use App\Models\Order;
use App\Enums\OrderStatus;
use App\Facades\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use LaravelDaily\Invoices\Classes\Buyer;
use LaravelDaily\Invoices\Classes\InvoiceItem;
use LaravelDaily\Invoices\Invoice;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Spatie\Browsershot\Browsershot;
use Throwable;

class CheckoutComponent extends Component
{
    public ?int $selectedShopId = null;
    public string $orderNotes = '';
    public bool $isProcessing = false;
    public bool $showInvoiceModal = false;
    public int $invoiceOrderId = 0;

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

    public function showInvoiceConfirmation($orderId): void
    {
        $this->showInvoiceModal = true;
        $this->invoiceOrderId = $orderId;
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
        $this->showInvoiceModal = false;

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

                $this->invoiceOrderId = $order->id;

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

                // Dispatch OrderCreated event
                \App\Events\OrderCreated::dispatch($order, Auth::id());
            });


            // Reset form after successful transaction
            $this->reset(['selectedShopId', 'orderNotes']);

            // Dispatch event to clear localStorage cart
            $this->dispatch('checkout');

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Order placed successfully!'
            ]);

            $this->showInvoiceConfirmation($this->invoiceOrderId);


        } catch (\Exception $e) {
            $this->addError('order', 'Failed to place order. Please try again. Error: ' . $e->getMessage());
        } finally {
            $this->isProcessing = false;
        }
    }

    public function goHome()
    {
        $this->redirect(route('main'));
    }

    public function exportOrderToPdf(Request $request)
    {
        $orderId = $request->invoiceOrderId;
        try {

            $order = Order::with(['items.product', 'shop', 'salesperson'])
                ->findOrFail($orderId);

            $customer = new Buyer([
                'serial' => $order->id,
                'date' => $order->updated_at->format('d M Y'),
                'invoice_records' => $order->items,
                'total' => $order->total_price
            ]);

            $invoice = Invoice::make()
                ->template('indirimgo')
                ->buyer($customer)
                ->discountByPercent(10)
                ->taxRate(18)
                ->addItem((new InvoiceItem())->pricePerUnit(2));


            $html = view('vendor.invoices.templates.indirimgo', compact('invoice'))->render();

            return response()->stream(function () use ($html) {
                print Browsershot::html($html)
                    ->format('A4')
                    ->noSandbox()
                    ->waitUntilNetworkIdle()
                    ->showBackground()
                    ->pdf();
            }, 200,
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="invoice.pdf"',
                ]);

        } catch (Throwable $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Failed to export order: ' . $e->getMessage()
            ]);

            dd($e->getMessage());
        }
    }

    public function downloadPdfAndRedirect()
    {
        // Set a flag to indicate PDF was requested
        $this->showInvoiceModal = false;
        
        // Redirect after a short delay
        $this->redirect(route('main'));
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
