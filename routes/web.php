<?php

use App\Livewire\CartComponent;
use App\Livewire\Frontend\MainComponent;
use App\Livewire\ProductComponent;
use App\Livewire\ProductsComponent;
use App\Livewire\ProfileComponent;
use Illuminate\Support\Facades\Route;

Route::get('test', function () { return 'test from maindomain';});

Route::get('/', MainComponent::class)->name('main');
Route::get('/product', ProductComponent::class);
Route::get('/products', ProductsComponent::class)->name('products');
Route::get('/profile', ProfileComponent::class)->name('account');
Route::get('/cart', CartComponent::class);

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        abort(404); // Make `/dashboard` inaccessible by default
    })->name('dashboard');
});

Route::get('test', function() {
    return 'test main domain';
});
