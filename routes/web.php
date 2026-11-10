<?php

use App\Http\Middleware\HandleLanguagePrefix;
use App\Livewire\Frontend\BranchOrdersComponent;
use App\Livewire\Frontend\CatalogComponent;
use App\Livewire\Frontend\CheckoutComponent;
use App\Livewire\Frontend\ContactusComponent;
use App\Livewire\Frontend\DeliveryAndReturnComponent;
use App\Livewire\Frontend\DemoMainComponent;
use App\Livewire\Frontend\DistanceSalesContractComponent;
use App\Livewire\Frontend\Errors\NotFound;
use App\Livewire\Frontend\MainComponent;
use App\Livewire\Frontend\PrivacyPolicyComponent;
use App\Livewire\Frontend\ProductsComponent;
use App\Livewire\Frontend\ShopCreationComponent;
use App\Livewire\Frontend\ShopOwnerProfile;
use Illuminate\Support\Facades\Route;

Route::get('/404', NotFound::class)->name('404');

Route::get('omar', function () {
    return 'test from main domain';
})->name('omar');

Route::get('/', MainComponent::class)
    ->middleware('shop.creation')
    ->name('main');

Route::get('/products/{category}', ProductsComponent::class)
    ->middleware('shop.creation')
    ->name('products');

// Route::get('/products', ProductsComponent::class)->name('products');
Route::get('/contactus', ContactusComponent::class)
    ->middleware('shop.creation')
    ->name('contactus');

Route::get('/privacy-policy', PrivacyPolicyComponent::class)
    ->middleware(['shop.creation', HandleLanguagePrefix::class])
    ->name('privacy-policy');

// Legal pages with language prefixes
Route::prefix('{locale}')->where(['locale' => 'en|tr|ar'])->group(function () {

    Route::get('/mesafeli-satis-sozlesmesi', DistanceSalesContractComponent::class)
        ->middleware(['shop.creation', HandleLanguagePrefix::class])
        ->name('distance-sales-contract');

    Route::get('/teslimat-ve-iade', DeliveryAndReturnComponent::class)
        ->middleware(['shop.creation', HandleLanguagePrefix::class])
        ->name('delivery-and-return');
});

Route::get('/checkout', CheckoutComponent::class)
    ->middleware(['auth', 'shop.creation'])
    ->name('checkout');

Route::get('/branch-orders', BranchOrdersComponent::class)
    ->middleware(['auth', 'shop.creation'])
    ->name('storefront.orders');

// Shop creation route - requires authentication
Route::get('/shop/create', ShopCreationComponent::class)
    ->middleware('auth')
    ->name('shop.create');

Route::get('/shop/profile', ShopOwnerProfile::class)
    ->middleware(['auth', 'shop.creation'])
    ->name('shop.profile');

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return redirect()->route('404');
    })->name('dashboard');
});

Route::get('test', function () {
    return 'test main domain';
});

Route::fallback(function () {
    return redirect()->route('404');
});

Route::post('invoice_pdf', [CheckoutComponent::class, 'exportOrderToPdf'])->name('invoice_pdf');

// Shop owner PDF export route
Route::post('shop/invoice_pdf/{orderId}/{shopId}', [ShopOwnerProfile::class, 'exportOrderToPdf'])->name('shop.invoice_pdf');

// For testing PDF generation
// Route::get('pdf', function() {
//    $order = \App\Models\Order::find(1);
//    return view('pdf.order-details', compact('order'));
// })->name('pdf.invoice');
//
// Route::get('pdf_1', function() {
//    $order = Order::with(['items.product', 'shop', 'salesperson'])
//        ->findOrFail(1);
//
//    $customer = new Buyer([
//        'serial' => $order->id,
//        'date' => $order->updated_at->format('d M Y'),
//        'invoice_records' => $order->items,
//        'total' => $order->total_price
//    ]);
//
//    $invoice = Invoice::make()
//        ->template('indirimgo')
//        ->buyer($customer)
//        ->discountByPercent(10)
//        ->taxRate(18)
//        ->addItem((new InvoiceItem())->pricePerUnit(2));
//
//    return view('vendor.invoices.templates.indirimgo', compact('invoice'));
// });

// Demo route for progress tracker
Route::get('/demo/progress-tracker', function () {
    return view('demo.progress-tracker');
})->name('demo.progress-tracker');

Route::get('/demo/main', DemoMainComponent::class)->name('demo.main');

Route::middleware(['auth', 'role:admin|customer_salesperson'])->group(function () {
    Route::get('/catalog/{category}', CatalogComponent::class)
        ->name('catalog');
});
