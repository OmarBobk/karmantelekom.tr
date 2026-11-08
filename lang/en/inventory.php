<?php

return [
    'navigation' => 'Inventory',
    'title' => 'Inventory',
    'breadcrumbs' => 'Breadcrumb',

    'unit_statuses' => [
        'available' => 'Available',
        'inspection' => 'Awaiting inspection',
        'in_repair' => 'In repair',
        'review' => 'Awaiting review',
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
        'review_approved' => 'Review approved',
        'sale' => 'Sale',
    ],

    'reasons' => [
        'opening_balance' => 'Opening balance',
        'count_correction' => 'Count correction',
        'physical_count' => 'Physical count',
        'found' => 'Found',
        'damaged' => 'Damaged',
        'lost' => 'Lost',
        'purchase' => 'Purchase receipt',
        'inspection' => 'Inspection',
        'repair' => 'Repair',
        'device_cost' => 'Device cost',
        'identity_correction' => 'Identity correction',
        'review' => 'Review',
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
        'setup_completed' => 'Initial inventory setup is complete for this branch. New supplier stock is received on its purchase invoice; a found device is reconciled for review.',
        'reconcile_reason_required' => 'Enter the reason for this reconciliation.',
        'review_verification_required' => 'Describe how the device’s source was verified.',
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
        'title' => 'Opening stock: register device',
        'help' => 'Initial setup only. Record a device already on hand in this branch. Its acquisition cost stays unknown.',
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
            'review' => 'Awaiting review',
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

    'setup' => [
        'title' => 'Initial inventory setup',
        'open' => 'Open',
        'completed' => 'Completed',
        'open_help' => 'Opening balances and opening device registration are allowed while setup is open. Complete it once the branch’s existing stock is recorded.',
        'completed_help' => 'Completed on :date by :name. New supplier stock is received on its purchase invoice.',
        'confirm' => 'Complete initial setup for this branch? Opening stock can no longer be recorded here afterwards.',
        'complete' => 'Complete setup',
        'completed_message' => 'Initial inventory setup completed.',
        'completed_product_help' => 'Initial setup is complete for this branch. New devices arrive through purchase receiving; use reconciliation below for discrepancies.',
    ],

    'reconcile' => [
        'title' => 'Reconcile a device',
        'help' => 'Scan or type the IMEI or serial of a device that differs from the records.',
        'code' => 'IMEI or serial',
        'find' => 'Find',
        'markMissing' => 'Mark missing',
        'markMissing_help' => 'This device is recorded in stock. If it is not on the shelf, record it as lost.',
        'markFound' => 'Mark found for review',
        'markFound_help' => 'This device was removed from stock. If it is on the shelf, return it awaiting review. It is not sellable until an owner approves it.',
        'in_review' => 'This device is already awaiting review. Approve or remove it on its device page.',
        'no_action' => 'This device is :status. No reconciliation applies.',
        'other_product' => 'This identifier belongs to :product in this branch.',
        'open_device' => 'Open device',
        'other_branch' => 'This identifier is registered at another branch. Do not record it here; ask the owner to check it.',
        'invalid' => 'Enter a 15-digit IMEI or a serial number.',
        'not_found_help' => 'No device has this identifier. Record it awaiting review; it is not sellable until an owner verifies its source.',
        'record_found' => 'Record for review',
        'clear' => 'Clear',
        'stale' => 'This device changed after you looked it up (now :status). Find it again before continuing.',
    ],

    'review' => [
        'approve' => 'Approve as sellable',
        'approve_help' => 'Only approve once the device’s source is verified.',
        'verification' => 'How the source was verified',
        'banner' => 'This device was found without a verified source. It is not sellable until an owner approves it or removes it from stock.',
    ],

    'remove' => [
        'title' => 'Remove from stock',
        'help' => 'The unit and its identifiers stay on record and their identifiers can never be reused.',
        'submit' => 'Remove unit',
        'confirm' => 'Remove this unit from available stock?',
        'already_removed' => 'This unit has been removed from stock.',
    ],
];
