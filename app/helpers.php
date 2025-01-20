<?php

if (!function_exists('money')) {
    function money($amount, $currency = null)
    {
        $currency = $currency ?? session('currency', config('app.currency', '$'));

        // dd($currency . ' ' . number_format($amount, 2));
        return number_format($amount, 2) . ' ' . $currency;
    }
} 