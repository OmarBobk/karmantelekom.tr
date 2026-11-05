<?php

return [
    'navigation' => 'Suppliers',
    'title' => 'Suppliers',
    'subtitle' => 'Basic contact details for product suppliers.',
    'search_label' => 'Search',
    'search_placeholder' => 'Name, contact, or phone',

    'fields' => [
        'name' => 'Supplier name',
        'contact_name' => 'Contact person',
        'phone' => 'Phone',
        'email' => 'Email',
        'tax_number' => 'Tax number',
        'address' => 'Address',
        'notes' => 'Notes',
        'status' => 'Status',
    ],

    'statuses' => [
        'active' => 'Active',
        'inactive' => 'Inactive',
    ],

    'actions' => [
        'create' => 'Add supplier',
        'edit' => 'Edit',
        'cancel' => 'Cancel',
        'save' => 'Save',
        'saving' => 'Saving…',
    ],

    'empty' => [
        'title' => 'No suppliers yet',
        'body' => 'Add the first supplier to keep their contact details.',
        'no_matches_title' => 'No matching suppliers',
        'no_matches_body' => 'Try another search term.',
    ],

    'form' => [
        'create_title' => 'Add supplier',
        'edit_title' => 'Edit supplier',
        'active' => 'Active supplier',
    ],

    'messages' => [
        'created' => 'Supplier added.',
        'updated' => 'Supplier updated.',
    ],
];
