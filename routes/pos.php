<?php

declare(strict_types=1);

use App\Http\Controllers\Pos\PosLocaleController;
use App\Http\Controllers\Pos\SaleReceiptController;
use App\Livewire\Pos\CashboxPage;
use App\Livewire\Pos\CashboxSessionPage;
use App\Livewire\Pos\CustomerAccountPage;
use App\Livewire\Pos\CustomerAccounts;
use App\Livewire\Pos\ExpensesPage;
use App\Livewire\Pos\NetworkListings;
use App\Livewire\Pos\PosCounter;
use App\Livewire\Pos\PosHome;
use App\Livewire\Pos\SalesHistory;
use Illuminate\Support\Facades\Route;

// Every page and action re-checks use_pos and store membership; see StorePolicy::usePos.
Route::get('/', PosHome::class)->name('home');
Route::post('locale', PosLocaleController::class)->name('locale');
Route::get('{store}', PosCounter::class)
    ->middleware('can:usePos,store')
    ->whereNumber('store')
    ->name('counter');
Route::get('{store}/sales', SalesHistory::class)
    ->middleware('can:usePos,store')
    ->whereNumber('store')
    ->name('sales');
Route::get('{store}/network', NetworkListings::class)
    ->middleware('can:discoverListings,store')
    ->whereNumber('store')
    ->name('network');
Route::get('{store}/customers', CustomerAccounts::class)
    ->middleware('can:receiveCustomerPayments,store')
    ->whereNumber('store')
    ->name('customers');
Route::get('{store}/customers/{customer}', CustomerAccountPage::class)
    ->middleware('can:receiveCustomerPayments,store')
    ->whereNumber(['store', 'customer'])
    ->scopeBindings()
    ->name('customers.show');
Route::get('{store}/cashbox', CashboxPage::class)
    ->middleware('can:manageCashbox,store')
    ->whereNumber('store')
    ->name('cashbox');
Route::get('{store}/cashbox/sessions/{session}', CashboxSessionPage::class)
    ->middleware('can:manageCashbox,store')
    ->whereNumber(['store', 'session'])
    ->name('cashbox.session');
Route::get('{store}/expenses', ExpensesPage::class)
    ->middleware('can:manageExpenses,store')
    ->whereNumber('store')
    ->name('expenses');
Route::get('{store}/sales/{sale}', SaleReceiptController::class)
    ->middleware(['can:usePos,store', 'can:view,sale'])
    ->whereNumber(['store', 'sale'])
    ->scopeBindings()
    ->name('sales.show');
