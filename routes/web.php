<?php

use App\Livewire\Frontend\ContactusComponent;
use App\Livewire\Frontend\Errors\NotFound;
use App\Livewire\Frontend\MainComponent;
use App\Livewire\Frontend\ProductsComponent;
use App\Models\Order;
use Illuminate\Support\Facades\Route;
use LaravelDaily\Invoices\Classes\Buyer;
use LaravelDaily\Invoices\Classes\InvoiceItem;
use LaravelDaily\Invoices\Invoice;


Route::get('/404', NotFound::class)->name('404');

Route::get('omar', function () { return 'test from main domain';})->name('omar');

Route::get('/', MainComponent::class)->name('main');

Route::get('/products/{category}', ProductsComponent::class)->name('products');
//Route::get('/products', ProductsComponent::class)->name('products');
Route::get('/contactus', ContactusComponent::class)->name('contactus');

Route::get('/checkout', \App\Livewire\Frontend\CheckoutComponent::class)
            ->middleware('auth')
            ->name('checkout');

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
//    dd('fallback');
});


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
//})->name('pdf.invoice');
