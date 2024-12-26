<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return 'subdomain';
});

Route::get('/test', function () {
    return 'test subdomain';
});
