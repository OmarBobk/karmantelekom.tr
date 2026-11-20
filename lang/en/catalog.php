<?php

return [
    'navigation' => 'Product catalog',
    'title' => 'Product catalog',
    'subtitle' => 'Product definitions shared by all stores. Creating a definition adds no stock.',

    'tabs' => [
        'definitions' => 'Product definitions',
        'settings' => 'Brands & categories',
    ],

    'tracking_modes' => [
        'quantity' => 'Quantity',
        'serialized' => 'Serialized (new)',
        'used_device' => 'Used device',
    ],

    'fields' => [
        'name' => 'Name',
        'model' => 'Model',
        'description' => 'Description',
        'tracking_mode' => 'Tracking mode',
        'sku' => 'SKU',
        'barcode' => 'Barcode',
        'brand' => 'Brand',
        'category' => 'Category',
        'status' => 'Status',
    ],

    'statuses' => [
        'active' => 'Active',
        'inactive' => 'Inactive',
    ],

    'actions' => [
        'create_definition' => 'Add product definition',
        'edit' => 'Edit',
        'cancel' => 'Cancel',
        'save' => 'Save',
        'saving' => 'Saving…',
        'add' => 'Add',
        'rename' => 'Rename',
        'activate' => 'Activate',
        'deactivate' => 'Deactivate',
        'generate_internal_barcode' => 'Generate internal number',
        'generating_internal_barcode' => 'Generating…',
        'continue_after_barcode' => 'Continue',
    ],

    'search' => [
        'by' => 'Search by',
        'by_name' => 'Name or model',
        'name_label' => 'Name or model',
        'identifier_label' => 'Exact identifier',
        'name_placeholder' => 'e.g. USB-C cable',
        'identifier_placeholder' => 'Type or scan the full identifier',
        'identifier_help' => 'Matches the identifier exactly, including leading zeros.',
    ],

    'empty' => [
        'definitions_title' => 'No product definitions yet',
        'definitions_body' => 'Add the first product definition to make it available to every store.',
        'no_matches_title' => 'No matching products',
        'no_matches_body' => 'Try another search term or check the identifier.',
        'brand' => 'No brands yet.',
        'category' => 'No categories yet.',
    ],

    'form' => [
        'create_title' => 'Add product definition',
        'edit_title' => 'Edit product definition',
        'tracking_help' => 'Controls how stores count this product. Locked once any stock is recorded.',
        'tracking_locked' => 'Tracking mode is locked because a store has inventory history for this product.',
        'none' => 'None',
        'active' => 'Available for use in stores',
        'no_stock_notice' => 'Saving a definition adds no stock. Record stock from each store’s inventory page.',
        'barcode_blank_help' => 'Leave blank to assign an internal number on save. Keep a manufacturer barcode when the package already has one.',
        'generate_internal_help' => 'Creates one internal number for handwriting on a sticker. Does not change stock or price.',
    ],

    'barcode_reveal' => [
        'title' => 'Write this number on the sticker',
        'help' => 'Copy the digits exactly. The same number works at the till and for a future label printer.',
        'barcode_label' => 'Internal number',
    ],

    'settings' => [
        'subtitle' => 'Brands and categories used to organize product definitions.',
        'brand_title' => 'Brands',
        'category_title' => 'Categories',
        'new_brand' => 'New brand name',
        'new_category' => 'New category name',
        'definitions_count' => '{0} No products|{1} 1 product|[2,*] :count products',
    ],

    'messages' => [
        'definition_created' => 'Product definition created.',
        'definition_updated' => 'Product definition updated.',
        'brand_created' => 'Brand added.',
        'brand_updated' => 'Brand updated.',
        'category_created' => 'Category added.',
        'category_updated' => 'Category updated.',
    ],

    'errors' => [
        'tracking_mode_locked' => 'The tracking mode cannot change after stock or a posted purchase invoice has been recorded for this product.',
        'identifier_taken' => 'This identifier is already used by another product.',
        'identifier_conflict' => 'This number already identifies a different product or device.',
        'internal_barcode_exhausted' => 'No free internal numbers remain (200001–299999). Enter a manufacturer barcode or free another internal number first.',
        'submission_conflict' => 'This create request was already used with different details. Close the form and add the product again.',
    ],
];
