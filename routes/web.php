<?php

use App\Livewire\MainComponent;
use App\Livewire\ProductComponent;
use App\Livewire\ProfileComponent;
use Illuminate\Support\Facades\Route;

Route::get('/', MainComponent::class)->name('main');
Route::get('/product', ProductComponent::class);
Route::get('/profile', ProfileComponent::class)->name('account');

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
});

Route::get('test', function() {
    return 'test main domain';
});
