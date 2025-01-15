<?php

if (!function_exists('money')) {
    function money($amount, $currency = '$'): string
    {
        return number_format($amount, 2) . ' ' . $currency;
    }
} 