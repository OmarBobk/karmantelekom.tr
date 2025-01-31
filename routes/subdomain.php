<?php

use App\Livewire\Backend\DashboardComponent;
use App\Livewire\Backend\Orders\OrdersComponent;
use App\Livewire\Backend\Products\ProductsComponent;
use App\Livewire\Backend\Products\SectionComponent;
use App\Livewire\Backend\Users\UsersComponent;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardComponent::class)->name('main');
Route::get('products', ProductsComponent::class)->name('products');
Route::get('sections', SectionComponent::class)->name('sections');
Route::get('orders', OrdersComponent::class)->name('orders');
Route::get('users', UsersComponent::class)->name('users');

Route::get('/dashboard', function () {
    abort(404); // Return a 404 error
})->name('dashboard');


