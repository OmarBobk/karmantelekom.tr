<?php

use App\Livewire\Frontend\ContactusComponent;
use App\Livewire\Frontend\Errors\NotFound;
use App\Livewire\Frontend\MainComponent;
use App\Livewire\Frontend\ProductsComponent;
use App\Livewire\Frontend\ProfileComponent;
use Illuminate\Support\Facades\Route;


Route::get('/404', NotFound::class)->name('404');

Route::get('omar', function () { return 'test from main domain';})->name('omar');

Route::get('/', MainComponent::class)->name('main');

Route::get('/products/{category}', ProductsComponent::class)->name('products');
//Route::get('/products', ProductsComponent::class)->name('products');
Route::get('/contactus', ContactusComponent::class)->name('contactus');

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
