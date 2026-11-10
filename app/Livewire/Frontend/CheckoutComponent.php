<?php

namespace App\Livewire\Frontend;

use App\Actions\Storefront\PlaceCartOrder;
use App\Actions\Storefront\StorefrontOrderGuard;
use App\Facades\Cart;
use App\Models\Order;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use LaravelDaily\Invoices\Classes\Buyer;
use LaravelDaily\Invoices\Classes\InvoiceItem;
use LaravelDaily\Invoices\Invoice;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
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

    protected function rules()
    {
        $rules = [
            'orderNotes' => 'nullable|string|max:1000',
        ];

        // Only require shop selection for non-shop owners
        if (! $this->isShopOwner) {
            $rules['selectedShopId'] = 'required|exists:shops,id';
        }

        return $rules;
    }

    protected array $messages = [
        'selectedShopId.required' => 'Please select a shop.',
        'selectedShopId.exists' => 'The selected shop is invalid.',
        'orderNotes.max' => 'Order notes cannot exceed 1000 characters.',
    ];

    public function mount()
    {
        // Auto-select shop for shop owners
        if (auth()->user() && auth()->user()->hasRole('shop_owner')) {
            $ownedShop = auth()->user()->ownedShop;
            if ($ownedShop) {
                $this->selectedShopId = $ownedShop->id;
            }
        }
    }

    // Check if current user is a shop owner
    public function getIsShopOwnerProperty(): bool
    {
        return auth()->user() && auth()->user()->hasRole('shop_owner');
    }

    // Get the shop owner's shop
    public function getOwnedShopProperty()
    {
        if ($this->isShopOwner) {
            return auth()->user()->ownedShop;
        }

        return null;
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
        $this->validate($this->rules());

        Log::info('Order Validation passed');

        if (! $this->hasItemsInCart()) {
            $this->addError('cart', 'Your cart is empty. Please add items before placing an order.');

            return;
        }

        if ($this->removeDisplayOnlyItems()) {
            return;
        }

        $this->isProcessing = true;
        $this->showInvoiceModal = false;

        try {
            $this->invoiceOrderId = app(PlaceCartOrder::class)(Auth::user(), (int) $this->selectedShopId, $this->orderNotes)->id;

            // Reset form after successful transaction
            $this->reset(['selectedShopId', 'orderNotes']);

            // Dispatch event to clear localStorage cart
            $this->dispatch('checkout');

            $this->dispatch('notify', [
                'type' => 'alert-success',
                'message' => 'Order placed successfully!',
            ]);

            $this->showInvoiceConfirmation($this->invoiceOrderId);

        } catch (ValidationException $exception) {
            if (! $this->removeDisplayOnlyItems()) {
                $this->addError('cart', $exception->getMessage());
            }
        } catch (\Exception $e) {
            $this->addError('order', 'Failed to place order. Please try again. Error: '.$e->getMessage());
        } finally {
            $this->isProcessing = false;
        }
    }

    /**
     * Drop display-only storefront stock from the cart and tell the buyer; true when any was removed.
     */
    private function removeDisplayOnlyItems(): bool
    {
        $displayOnly = StorefrontOrderGuard::displayOnlyProductIds($this->cart->items()->pluck('product_id'));

        if ($displayOnly === []) {
            return false;
        }

        $this->cart->items()->whereIn('product_id', $displayOnly)->delete();
        $this->addError('cart', __('main.storefront_display_only'));

        return true;
    }

    public function goHome()
    {
        $this->redirect(route('main'));
    }

    public function exportOrderToPdf(Request $request)
    {
        $orderId = $request->invoiceOrderId;
        try {
            $order = Order::with(['items.product', 'shop', 'customer'])
                ->findOrFail($orderId);

            $customer = new Buyer([
                'serial' => $order->id,
                'date' => $order->updated_at->format('d M Y'),
                'invoice_records' => $order->items,
                'total' => $order->total_price,
            ]);

            $invoice = Invoice::make()
                ->template('indirimgo')
                ->buyer($customer)
                ->discountByPercent(10)
                ->taxRate(18)
                ->addItem((new InvoiceItem)->pricePerUnit(2));

            $html = view('vendor.invoices.templates.indirimgo', compact('invoice'))->render();

            return response()->stream(function () use ($html) {
                echo Browsershot::html($html)
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
                'type' => 'alert-error',
                'message' => 'Failed to export order: '.$e->getMessage(),
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
            'isShopOwner' => $this->isShopOwner,
            'ownedShop' => $this->ownedShop,
        ]);
    }
}
