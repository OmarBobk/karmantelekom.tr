<?php

use App\Livewire\Backend\Categories\CategoryComponent;
use App\Livewire\Backend\DashboardComponent;
use App\Livewire\Backend\Products\ProductsComponent;
use App\Livewire\Backend\Products\SectionComponent;
use App\Livewire\Backend\Suppliers\SupplierComponent;
use App\Livewire\Backend\Users\UsersComponent;
use App\Livewire\Backend\Errors\NotFound;
use Illuminate\Support\Facades\Route;

Route::get('login', function () {
    abort(404); // Return a 404 error
});

Route::get('/', DashboardComponent::class)->name('main');
Route::get('categories', CategoryComponent::class)->name('categories');
Route::get('suppliers', SupplierComponent::class)->name('suppliers');
Route::get('products', ProductsComponent::class)->name('products');
Route::get('sections', SectionComponent::class)->name('sections');
Route::get('users', UsersComponent::class)->name('users');

Route::get('sections', SectionComponent::class)->name('sections');

Route::get('/dashboard', function () {
    abort(404); // Return a 404 error
})->name('dashboard');

Route::fallback(function () {
    return redirect()->route('404');
});


Route::get('/404', NotFound::class)->name('404');

