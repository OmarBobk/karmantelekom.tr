<?php

use App\Livewire\Backend\DashboardComponent;
use App\Livewire\Backend\ProductsComponent;
use Illuminate\Support\Facades\Route;


Route::get('/', DashboardComponent::class)->name('main');
Route::get('products', ProductsComponent::class)->name('products');


Route::get('/dashboard', function () {
    abort(404); // Return a 404 error
})->name('dashboard');

