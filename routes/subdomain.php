<?php

use App\Livewire\Backend\Ads\AdsComponent;
use App\Livewire\Backend\Categories\CategoryComponent;
use App\Livewire\Backend\DashboardComponent;
use App\Livewire\Backend\Errors\NotFound;
use App\Livewire\Backend\Products\ProductsComponent;
use App\Livewire\Backend\Products\SectionComponent;
use App\Livewire\Backend\Settings\SettingsComponent;
use App\Livewire\Backend\Tags\TagComponent;
use App\Livewire\Backend\Users\UsersComponent;
use Illuminate\Support\Facades\Route;

Route::get('login', function () {
    abort(404); // Return a 404 error
});

Route::get('/', DashboardComponent::class)->name('main');
Route::get('categories', CategoryComponent::class)->name('categories');
Route::get('products', ProductsComponent::class)->name('products');
Route::get('tags', TagComponent::class)->name('tags');
Route::get('sections', SectionComponent::class)->name('sections');
Route::get('ads', AdsComponent::class)->name('ads');
Route::get('users', UsersComponent::class)->name('users');
Route::get('settings', SettingsComponent::class)->name('settings');

Route::get('sections', SectionComponent::class)->name('sections');

Route::get('/dashboard', function () {
    abort(404); // Return a 404 error
})->name('dashboard');

Route::fallback(function () {
    return redirect()->route('404');
});


Route::get('/404', NotFound::class)->name('404');

