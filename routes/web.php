<?php

use App\Livewire\Frontend\CatalogComponent;
use App\Livewire\Frontend\CheckoutComponent;
use App\Livewire\Frontend\ContactusComponent;
use App\Livewire\Frontend\Errors\NotFound;
use App\Livewire\Frontend\MainComponent;
use App\Livewire\Frontend\ProductsComponent;
use App\Livewire\Frontend\ShopCreationComponent;
use Illuminate\Support\Facades\Route;


Route::get('/404', NotFound::class)->name('404');

Route::get('omar', function () { return 'test from main domain';})->name('omar');

Route::get('/', MainComponent::class)
    ->middleware('shop.creation')
    ->name('main');

Route::get('/products/{category}', ProductsComponent::class)
    ->middleware('shop.creation')
    ->name('products');

//Route::get('/products', ProductsComponent::class)->name('products');
Route::get('/contactus', ContactusComponent::class)
    ->middleware('shop.creation')
    ->name('contactus');

Route::get('/privacy-policy', \App\Livewire\Frontend\PrivacyPolicyComponent::class)
    ->middleware(['shop.creation', \App\Http\Middleware\HandleLanguagePrefix::class])
    ->name('privacy-policy');

// Legal pages with language prefixes
Route::prefix('{locale}')->where(['locale' => 'en|tr|ar'])->group(function () {


    Route::get('/mesafeli-satis-sozlesmesi', \App\Livewire\Frontend\DistanceSalesContractComponent::class)
        ->middleware(['shop.creation', \App\Http\Middleware\HandleLanguagePrefix::class])
        ->name('distance-sales-contract');

    Route::get('/teslimat-ve-iade', \App\Livewire\Frontend\DeliveryAndReturnComponent::class)
        ->middleware(['shop.creation', \App\Http\Middleware\HandleLanguagePrefix::class])
        ->name('delivery-and-return');
});

Route::get('/checkout', CheckoutComponent::class)
    ->middleware(['auth', 'shop.creation'])
    ->name('checkout');

// Shop creation route - requires authentication
Route::get('/shop/create', ShopCreationComponent::class)
    ->middleware('auth')
    ->name('shop.create');

Route::get('/shop/profile', \App\Livewire\Frontend\ShopOwnerProfile::class)
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

Route::get('test', function() {
    return 'test main domain';
});

Route::fallback(function () {
    dd('fallback');
});

Route::post('invoice_pdf', [CheckoutComponent::class, 'exportOrderToPdf'])->name('invoice_pdf');

// Shop owner PDF export route
Route::post('shop/invoice_pdf/{orderId}/{shopId}', [\App\Livewire\Frontend\ShopOwnerProfile::class, 'exportOrderToPdf'])->name('shop.invoice_pdf');

// For testing PDF generation
//Route::get('pdf', function() {
//    $order = \App\Models\Order::find(1);
//    return view('pdf.order-details', compact('order'));
//})->name('pdf.invoice');
//
//Route::get('pdf_1', function() {
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
//});

// Demo route for progress tracker
Route::get('/demo/progress-tracker', function () {
    return view('demo.progress-tracker');
})->name('demo.progress-tracker');

Route::get('/demo/main', \App\Livewire\Frontend\DemoMainComponent::class)->name('demo.main');


Route::middleware(['auth', 'role:admin|customer_salesperson'])->group(function () {
    Route::get('/catalog/{category}', CatalogComponent::class)
        ->name('catalog');
});
