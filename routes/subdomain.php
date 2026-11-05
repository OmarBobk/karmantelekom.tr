<?php

use App\Livewire\Backend\ActivitiesComponent;
use App\Livewire\Backend\Ads\AdsComponent;
use App\Livewire\Backend\Catalog\CatalogComponent;
use App\Livewire\Backend\Categories\CategoryComponent;
use App\Livewire\Backend\DashboardComponent;
use App\Livewire\Backend\Errors\NotFound;
use App\Livewire\Backend\Orders\OrdersManager;
use App\Livewire\Backend\Products\ProductsComponent;
use App\Livewire\Backend\Products\SectionComponent;
use App\Livewire\Backend\Settings\SettingsComponent;
use App\Livewire\Backend\Shops\ShopComponent;
use App\Livewire\Backend\Shops\ShopProfileComponent;
use App\Livewire\Backend\Stores\StoreDetail;
use App\Livewire\Backend\Stores\StoresIndex;
use App\Livewire\Backend\Tags\TagComponent;
use App\Livewire\Backend\Users\UsersComponent;
use App\Models\Store;
use Illuminate\Support\Facades\Route;

Route::get('login', function () {
    abort(404); // Return a 404 error
});

Route::get('/', DashboardComponent::class)->name('main');
Route::get('categories', CategoryComponent::class)->name('categories');
Route::get('products', ProductsComponent::class)->name('products');
Route::get('catalog', CatalogComponent::class)->name('catalog');
Route::get('tags', TagComponent::class)->name('tags');
Route::get('sections', SectionComponent::class)->name('sections');
Route::get('ads', AdsComponent::class)->name('ads');
Route::get('users', UsersComponent::class)->name('users');
Route::get('settings', SettingsComponent::class)->name('settings');
Route::get('shops', ShopComponent::class)->name('shops');
Route::get('shops/{shop}', ShopProfileComponent::class)->name('shop');
Route::get('orders', OrdersManager::class)->name('orders');
Route::get('activities', ActivitiesComponent::class)->name('activities');
Route::get('stores', StoresIndex::class)
    ->middleware('can:viewAny,'.Store::class)
    ->name('stores');
Route::get('stores/{store}', StoreDetail::class)
    ->middleware('can:view,store')
    ->name('stores.show');

// PDF Export Route
Route::post('invoice_pdf/{orderId}', [OrdersManager::class, 'exportOrderToPdf'])->name('invoice_pdf');

Route::get('sections', SectionComponent::class)->name('sections');

Route::get('/dashboard', function () {
    return redirect()->route('404');
})->name('dashboard');

Route::fallback(function () {
    return redirect()->route('404');
});

Route::get('/404', NotFound::class)->name('404');
