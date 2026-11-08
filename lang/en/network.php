<?php

return [
    'nav' => 'Other branches',

    'fields' => [
        'condition' => 'Condition',
        'branch' => 'Branch',
        'price' => 'Asking price',
    ],

    'discovery' => [
        'title' => 'Devices at other branches',
        'help' => 'Devices other branches have shared and can sell now. Ask the branch before promising one to a customer.',
        'search' => 'Product or model',
        'search_placeholder' => 'e.g. iPhone 13',
        'any' => 'Any condition',
        'any_branch' => 'All branches',
        'apply' => 'Search',
        'clear' => 'Clear',
        'empty' => 'No other branch is sharing an available device right now.',
        'empty_filtered' => 'No shared devices match these filters.',
    ],

    'listing' => [
        'title' => 'Store Network',
        'not_shared' => 'Not shared',
        'visible' => 'Shared with other branches',
        'hidden' => 'Shared, but hidden from other branches',
        'since' => 'by :name,',
        'publish' => 'Share with other branches',
        'unpublish' => 'Stop sharing',
        'published_message' => 'The device is now visible to other branches.',
        'unpublished_message' => 'The device is no longer shared.',
    ],

    'errors' => [
        'not_individual' => 'Only individually tracked devices can be shared.',
        'product_inactive' => 'The product is inactive, so it cannot be shared or sold.',
        'unit_unavailable' => 'The device is :status, so other branches cannot see it.',
        'not_priced' => 'Set a selling price before sharing this device.',
    ],
];
