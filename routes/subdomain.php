<?php

use App\Livewire\Backend\ActivitiesComponent;
use App\Livewire\Backend\Ads\AdsComponent;
use App\Livewire\Backend\Catalog\CatalogComponent;
use App\Livewire\Backend\Categories\CategoryComponent;
use App\Livewire\Backend\DashboardComponent;
use App\Livewire\Backend\Errors\NotFound;
use App\Livewire\Backend\Inventory\InventoryStores;
use App\Livewire\Backend\Inventory\ProductInventory;
use App\Livewire\Backend\Inventory\StoreInventory;
use App\Livewire\Backend\Inventory\UnitDetail;
use App\Livewire\Backend\OperationalCatalog\CatalogSettings;
use App\Livewire\Backend\OperationalCatalog\DefinitionsIndex;
use App\Livewire\Backend\Orders\OrdersManager;
use App\Livewire\Backend\Products\ProductsComponent;
use App\Livewire\Backend\Products\SectionComponent;
use App\Livewire\Backend\Purchases\PurchaseDraft;
use App\Livewire\Backend\Purchases\PurchaseShow;
use App\Livewire\Backend\Purchases\PurchasesIndex;
use App\Livewire\Backend\Purchases\ReceivePurchaseForm;
use App\Livewire\Backend\Purchases\SupplierAccountPage;
use App\Livewire\Backend\Reports\OperationsDashboard;
use App\Livewire\Backend\Settings\SettingsComponent;
use App\Livewire\Backend\Shops\ShopComponent;
use App\Livewire\Backend\Shops\ShopProfileComponent;
use App\Livewire\Backend\Storefront\StorefrontPublisher;
use App\Livewire\Backend\Stores\StoreDetail;
use App\Livewire\Backend\Stores\StoresIndex;
use App\Livewire\Backend\Suppliers\SuppliersIndex;
use App\Livewire\Backend\Tags\TagComponent;
use App\Livewire\Backend\Users\UsersComponent;
use App\Models\CatalogBrand;
use App\Models\OperationalSupplier;
use App\Models\ProductDefinition;
use App\Models\Purchase;
use App\Models\Store;
use App\Models\StorefrontListing;
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

// Operations dashboard, catalog, suppliers, and store inventory (owner-only).
Route::prefix('operations')->group(function () {
    Route::get('dashboard', OperationsDashboard::class)
        ->middleware('can:viewOperationsDashboard,'.Store::class)
        ->name('operations.dashboard');
    Route::get('dashboard/{store}', OperationsDashboard::class)
        ->middleware(['can:viewOperationsDashboard,'.Store::class, 'can:viewAllSales,store'])
        ->whereNumber('store')
        ->name('operations.dashboard.store');
    Route::get('catalog', DefinitionsIndex::class)
        ->middleware('can:viewAny,'.ProductDefinition::class)
        ->name('catalog.definitions');
    Route::get('catalog/settings', CatalogSettings::class)
        ->middleware('can:viewAny,'.CatalogBrand::class)
        ->name('catalog.settings');
    Route::get('suppliers', SuppliersIndex::class)
        ->middleware('can:viewAny,'.OperationalSupplier::class)
        ->name('suppliers');
    Route::get('inventory', InventoryStores::class)
        ->middleware('can:viewAnyInventory,'.Store::class)
        ->name('inventory');
    Route::get('inventory/{store}', StoreInventory::class)
        ->middleware('can:viewInventory,store')
        ->name('inventory.store');
    Route::get('inventory/{store}/products/{productDefinition}', ProductInventory::class)
        ->middleware('can:viewInventory,store')
        ->name('inventory.product');
    Route::get('inventory/{store}/units/{unit}', UnitDetail::class)
        ->middleware('can:viewInventory,store')
        ->scopeBindings()
        ->name('inventory.unit');
    Route::get('suppliers/{supplier}/account', SupplierAccountPage::class)
        ->middleware('can:viewAccount,supplier')
        ->name('suppliers.account');
    Route::get('purchases', PurchasesIndex::class)
        ->middleware('can:viewAny,'.Purchase::class)
        ->name('purchases');
    Route::get('purchases/{purchase}', PurchaseShow::class)
        ->middleware('can:view,purchase')
        ->name('purchases.show');
    Route::get('purchases/{purchase}/edit', PurchaseDraft::class)
        ->middleware('can:update,purchase')
        ->name('purchases.edit');
    Route::get('purchases/{purchase}/receive', ReceivePurchaseForm::class)
        ->middleware('can:receive,purchase')
        ->name('purchases.receive');
    Route::get('storefront', StorefrontPublisher::class)
        ->middleware('can:viewAny,'.StorefrontListing::class)
        ->name('storefront');
});

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
