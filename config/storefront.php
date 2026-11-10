<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storefront Orders for Branch Stock
    |--------------------------------------------------------------------------
    |
    | When enabled, signed-in buyers may order linked branch stock through the
    | checkout for pickup at one branch, which confirms and holds the stock.
    | When disabled, linked branch stock stays display-only.
    |
    */

    'branch_orders' => (bool) env('STOREFRONT_BRANCH_ORDERS', false),

];
