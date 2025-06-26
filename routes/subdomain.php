<?php

use App\Livewire\Backend\Ads\AdsComponent;
use App\Livewire\Backend\Categories\CategoryComponent;
use App\Livewire\Backend\DashboardComponent;
use App\Livewire\Backend\Errors\NotFound;
use App\Livewire\Backend\Products\ProductsComponent;
use App\Livewire\Backend\Products\SectionComponent;
use App\Livewire\Backend\Settings\SettingsComponent;
use App\Livewire\Backend\Tags\TagComponent;
use App\Livewire\Backend\Users\UsersComponent;
use Illuminate\Support\Facades\Route;

Route::get('login', function () {
    abort(404); // Return a 404 error
});

Route::get('/', DashboardComponent::class)->name('main');
Route::get('categories', CategoryComponent::class)->name('categories');
Route::get('products', ProductsComponent::class)->name('products');
Route::get('tags', TagComponent::class)->name('tags');
Route::get('sections', SectionComponent::class)->name('sections');
Route::get('ads', AdsComponent::class)->name('ads');
Route::get('users', UsersComponent::class)->name('users');
Route::get('settings', SettingsComponent::class)->name('settings');
Route::get('shops', \App\Livewire\Backend\Shops\ShopComponent::class)->name('shops');
Route::get('shops/{shop}', \App\Livewire\Backend\Shops\ShopProfileComponent::class)->name('shop');
Route::get('orders', \App\Livewire\Backend\Orders\OrdersManager::class)->name('orders');


// PDF Export Route
Route::get('/orders/{order}/pdf', function (\App\Models\Order $order) {
    // Force HTTPS for this route
    if (!request()->secure() && app()->environment('production') && !app()->environment('local')) {
        return redirect()->secure(request()->url());
    }

    // Load the order with relationships
    $order->load(['items.product', 'shop', 'salesperson']);

    // Generate HTML content
    $html = view('pdf.order-details', compact('order'))->render();

    // Create PDF using DomPDF
    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);

    // Set paper size and orientation
    $pdf->setPaper('A4', 'portrait');

    // Generate filename
    $filename = "order-{$order->id}-" . now()->format('Y-m-d') . ".pdf";

    // Return PDF as download
    return $pdf->download($filename);
})->name('orders.pdf');

Route::get('sections', SectionComponent::class)->name('sections');

Route::get('/dashboard', function () {
    return redirect()->route('404');
})->name('dashboard');

Route::fallback(function () {
    return redirect()->route('404');
});


Route::get('/404', NotFound::class)->name('404');

