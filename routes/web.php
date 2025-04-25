<?php

use App\Livewire\Frontend\Errors\NotFound;
use App\Livewire\Frontend\MainComponent;
use App\Livewire\Frontend\ProfileComponent;
use App\Livewire\ProductComponent;
use App\Livewire\ProductsComponent;
use Illuminate\Support\Facades\Route;


Route::get('/404', NotFound::class)->name('404');

Route::get('omar', function () { return 'test from main domain';})->name('omar');

Route::get('/', MainComponent::class)->name('main');
Route::get('/product', ProductComponent::class);
Route::get('/products', ProductsComponent::class)->name('products');
Route::get('/profile', ProfileComponent::class)->name('account');

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

Route::fallback(function () {
    dd('fallback');
});
