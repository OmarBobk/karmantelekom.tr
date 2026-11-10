<?php

namespace App\Livewire\Frontend;

use App\Actions\Storefront\BranchOrderHolds;
use App\Actions\Storefront\PlaceCartOrder;
use App\Actions\Storefront\QuoteBranchOrder;
use App\Actions\Storefront\StorefrontOrderGuard;
use App\Actions\Storefront\StorefrontPricing;
use App\Actions\Storefront\SubmitBranchOrder;
use App\Facades\Cart;
use App\Models\Order;
use App\Models\Shop;
use App\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LaravelDaily\Invoices\Classes\Buyer;
use LaravelDaily\Invoices\Classes\InvoiceItem;
use LaravelDaily\Invoices\Invoice;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Browsershot\Browsershot;
use Throwable;

class CheckoutComponent extends Component
{
    public ?int $selectedShopId = null;

    /** The branch that fulfills a cart of linked branch stock. */
    public ?int $selectedStoreId = null;

    /** Makes a repeated submit of this checkout return the same branch order. */
    #[Locked]
    public string $submissionKey = '';

    /**
     * Branch totals in minor units as last shown, by store id; submission is refused if the
     * server's prices no longer match the one the buyer chose.
     *
     * @var array<int, int>
     */
    #[Locked]
    public array $shownBranchTotals = [];

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
        $this->submissionKey = (string) Str::uuid();

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

        if (BranchOrderHolds::enabled()) {
            $kind = QuoteBranchOrder::kind($this->cartQuantities());

            if ($kind === QuoteBranchOrder::MIXED) {
                $this->addError('cart', __('main.branch_orders.errors.mixed_cart'));

                return;
            }

            if ($kind === QuoteBranchOrder::BRANCH) {
                $this->placeBranchOrder();

                return;
            }
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
     * Submit the cart as a pending order for pickup at the chosen branch, at the total shown.
     */
    private function placeBranchOrder(): void
    {
        $shownTotal = $this->shownBranchTotals[(int) $this->selectedStoreId] ?? null;

        if ($this->selectedStoreId === null || $shownTotal === null) {
            $this->addError('selectedStoreId', __('main.branch_orders.errors.choose_branch'));

            return;
        }

        try {
            app(SubmitBranchOrder::class)(Auth::user(), (int) $this->selectedShopId, (int) $this->selectedStoreId, $shownTotal, $this->submissionKey, $this->orderNotes);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }

            return;
        }

        $this->reset(['selectedShopId', 'selectedStoreId', 'orderNotes']);
        // Submission already removed the ordered items; the browser loads what is left, such as
        // items added meanwhile, instead of asking the server to clear the cart again.
        $this->dispatch('checkout', ...Cart::browserCart((int) Auth::id()));
        session()->flash('success', __('main.branch_orders.submitted'));
        $this->redirect(route('storefront.orders'));
    }

    /**
     * @return Collection<int, int> Cart quantity by product id.
     */
    private function cartQuantities(): Collection
    {
        return $this->cart->items()->get()->mapWithKeys(fn ($item): array => [(int) $item->product_id => (int) $item->quantity]);
    }

    /**
     * Drop display-only storefront stock from the cart and tell the buyer; true when any was removed.
     */
    private function removeDisplayOnlyItems(): bool
    {
        $displayOnly = StorefrontOrderGuard::displayOnlyProductIds($this->cart->items()->pluck('product_id'), Auth::user());

        if ($displayOnly === []) {
            return false;
        }

        $cart = $this->cart;
        $cart->items()->whereIn('product_id', $displayOnly)->delete();
        CartService::advanceRevision($cart);
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
        $cartKind = QuoteBranchOrder::LEGACY;
        $branches = [];
        $priceGaps = [];
        $orderable = true;

        if (BranchOrderHolds::enabled() && $this->cart->items->isNotEmpty()) {
            $quantities = $this->cartQuantities();
            $cartKind = QuoteBranchOrder::kind($quantities);
            $channel = $cartKind === QuoteBranchOrder::BRANCH ? StorefrontPricing::orderChannel(auth()->user(), $quantities->keys()) : null;
            $orderable = $cartKind !== QuoteBranchOrder::BRANCH || $channel !== null;
            $priceGaps = $channel === null ? [] : QuoteBranchOrder::priceGaps($quantities, $channel);
            $branches = $channel !== null && $priceGaps === [] ? app(QuoteBranchOrder::class)->branches($quantities, $channel) : [];
        }

        $this->shownBranchTotals = collect($branches)->mapWithKeys(fn (array $branch): array => [$branch['store_id'] => $branch['total_minor']])->all();

        if ($this->selectedStoreId !== null && ! isset($this->shownBranchTotals[$this->selectedStoreId])) {
            $this->selectedStoreId = null;
        }

        if ($this->selectedStoreId === null && count($branches) === 1) {
            $this->selectedStoreId = $branches[0]['store_id'];
        }

        return view('livewire.frontend.checkout-component', [
            'shops' => $shops,
            'cartKind' => $cartKind,
            'branches' => $branches,
            'selectedBranch' => collect($branches)->firstWhere('store_id', $this->selectedStoreId),
            'orderable' => $orderable,
            'priceGapNames' => $this->cart->items->whereIn('product_id', $priceGaps)->map(fn ($item): string => (string) $item->product?->name)->values()->all(),
            'productNames' => $this->cart->items->mapWithKeys(fn ($item): array => [(int) $item->product_id => (string) $item->product?->name])->all(),
            'cart' => $this->cart,
            'cartTotal' => $this->cartTotal,
            'isShopOwner' => $this->isShopOwner,
            'ownedShop' => $this->ownedShop,
        ]);
    }
}
