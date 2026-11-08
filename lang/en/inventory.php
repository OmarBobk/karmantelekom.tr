<?php

return [
    'navigation' => 'Inventory',
    'title' => 'Inventory',
    'breadcrumbs' => 'Breadcrumb',

    'unit_statuses' => [
        'available' => 'Available',
        'inspection' => 'Awaiting inspection',
        'in_repair' => 'In repair',
        'removed' => 'Removed',
        'sold' => 'Sold',
    ],

    'conditions' => [
        'new' => 'New',
        'a' => 'A',
        'b' => 'B',
        'c' => 'C',
        'for_parts' => 'For parts',
    ],

    'movement_types' => [
        'opening_balance' => 'Opening balance',
        'adjustment_increase' => 'Increase',
        'adjustment_decrease' => 'Decrease',
        'purchase_receipt' => 'Purchase receipt',
        'inspection' => 'Inspection',
        'repair_started' => 'Repair started',
        'repair_completed' => 'Repair completed',
        'repair_cancelled' => 'Repair cancelled',
        'device_cost_recorded' => 'Cost recorded',
        'device_cost_reversed' => 'Cost corrected',
        'identifier_corrected' => 'Identifier corrected',
        'sale' => 'Sale',
    ],

    'reasons' => [
        'opening_balance' => 'Opening balance',
        'count_correction' => 'Count correction',
        'found' => 'Found',
        'damaged' => 'Damaged',
        'lost' => 'Lost',
        'purchase' => 'Purchase receipt',
        'inspection' => 'Inspection',
        'repair' => 'Repair',
        'device_cost' => 'Device cost',
        'identity_correction' => 'Identity correction',
        'sale' => 'Sale',
    ],

    'identifier_types' => [
        'imei' => 'IMEI',
        'serial' => 'Serial number',
    ],

    'fields' => [
        'quantity' => 'Quantity',
        'note' => 'Note',
        'reason' => 'Reason',
        'counted_quantity' => 'Counted total',
        'product' => 'Product',
        'on_hand' => 'On hand',
        'condition' => 'Condition',
        'imei' => 'IMEI',
        'secondary_imei' => 'Second IMEI (dual SIM)',
        'serial' => 'Serial number',
        'store' => 'Store',
        'removed_at' => 'Removed at',
    ],

    'errors' => [
        'invalid_imei' => 'An IMEI must be 15 digits with a valid check digit.',
        'quantity_range' => 'Quantity must be a whole number between 1 and :max.',
        'requires_quantity_tracking' => 'This product is not tracked by quantity.',
        'opening_balance_exists' => 'An opening balance is not allowed once this store has stock history for the product. Use a physical count.',
        'count_range' => 'The counted total must be a whole number between 0 and :max.',
        'count_reason_required' => 'Enter the reason for this count.',
        'count_stale' => 'Stock changed after this count started. Count the shelf again, then restart the count.',
        'reason_mismatch' => 'The selected reason does not match this operation.',
        'note_required' => 'A note is required when decreasing stock.',
        'insufficient_stock' => 'Not enough stock. Currently available: :available.',
        'requires_unit_tracking' => 'This product is not tracked by unit.',
        'condition_not_allowed' => 'The selected condition is not allowed for this product type.',
        'identifier_required' => 'Enter at least one IMEI or serial number.',
        'primary_imei_required' => 'Enter the first IMEI before the second IMEI.',
        'duplicate_imei_pair' => 'The second IMEI must differ from the first.',
        'imei_taken' => 'This IMEI is already registered to another device.',
        'serial_taken' => 'This serial number is already registered for this product.',
        'unit_not_available' => 'This unit is not available in stock.',
        'submission_conflict' => 'This form was already used for a different operation. Review the details and submit again.',
    ],

    'messages' => [
        'recorded' => 'Inventory movement recorded.',
        'already_recorded' => 'This operation was already recorded and was not repeated.',
        'count_matches' => 'The count matches the current balance. No change was recorded.',
    ],

    'actions' => [
        'open_store' => 'Open store inventory',
    ],

    'stores' => [
        'subtitle' => 'Choose a store to view its stock and record changes.',
        'empty_title' => 'No stores available',
        'empty_body' => 'You do not have access to any store.',
        'quantity_on_hand' => 'Quantity on hand',
        'available_units' => 'Available units',
    ],

    'store' => [
        'subtitle' => 'Products with inventory history in this store.',
        'find_product_title' => 'Stock a product',
        'find_product_help' => 'Search by name or model, or type a full SKU or barcode.',
        'find_product_placeholder' => 'Product name, SKU, or barcode',
        'no_catalog_matches' => 'No matching active product definition. Add it to the product catalog first.',
        'stocked_title' => 'Store stock',
        'filter_label' => 'Filter by name or model',
        'empty_title' => 'No stock in this store yet',
        'empty_body' => 'Find a product above and record its opening balance.',
        'no_matches_title' => 'No matching products',
        'no_matches_body' => 'Try another search term.',
    ],

    'product' => [
        'available_units' => 'Available units',
        'quantity_in_store' => 'Quantity in :store',
        'read_only' => 'You can view this stock but not change it.',
    ],

    'opening' => [
        'title' => 'Opening balance',
        'help' => 'Initial setup only: recorded once, before any other movement in this store. Later changes come from purchase receipts, sales, and physical counts.',
        'submit' => 'Record opening balance',
    ],

    'count' => [
        'title' => 'Physical count',
        'help' => 'Start the count before counting the shelf. Any sale or receipt during the count makes it stale.',
        'in_progress_help' => 'Enter the total you counted on the shelf, including zero. The difference is recorded in the history.',
        'start' => 'Start count',
        'restart' => 'Restart count',
        'quantity_help' => 'The full physical total, not the difference.',
        'submit' => 'Record count',
        'cancel' => 'Cancel',
    ],

    'receiving' => [
        'help' => 'Stock from a supplier is received on its purchase invoice.',
        'link' => 'Open purchases',
    ],

    'register' => [
        'title' => 'Register unit',
        'help' => 'Register one available device in this store’s stock.',
        'identifier_help' => 'An IMEI is unique across the whole system and a serial number is unique per product, even after removal.',
        'submit' => 'Register unit',
    ],

    'form' => [
        'optional' => 'optional',
        'required' => 'required',
        'choose' => 'Choose…',
        'saving' => 'Recording…',
    ],

    'units' => [
        'title' => 'Units',
        'filter' => 'Filter units by status',
        'filters' => [
            'available' => 'Available',
            'in_service' => 'Inspection & repair',
            'removed' => 'Removed',
            'sold' => 'Sold',
            'all' => 'All',
        ],
        'empty' => 'No units in this view.',
    ],

    'history' => [
        'title' => 'Movement history',
        'subtitle' => 'Permanent record; entries cannot be edited or deleted.',
        'empty' => 'No inventory movements yet.',
        'balance_after' => 'Balance after',
        'previous_balance' => 'Previous balance',
        'counted_total' => 'Counted total',
        'status_change' => 'Status',
    ],

    'remove' => [
        'title' => 'Remove from stock',
        'help' => 'The unit and its identifiers stay on record and their identifiers can never be reused.',
        'submit' => 'Remove unit',
        'confirm' => 'Remove this unit from available stock?',
        'already_removed' => 'This unit has been removed from stock.',
    ],
];
