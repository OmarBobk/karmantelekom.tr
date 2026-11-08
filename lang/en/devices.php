<?php

return [
    'to' => 'to',
    'recorded_by' => 'Recorded by :name',

    'inspection_outcomes' => [
        'ready' => 'Ready for sale',
        'needs_repair' => 'Needs repair',
    ],

    'repair_statuses' => [
        'open' => 'In progress',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ],

    'repairer_types' => [
        'external' => 'External repair shop',
        'in_house' => 'In-house technician',
    ],

    'cost_categories' => [
        'repair_parts' => 'Repair parts',
        'repair_labor' => 'Repair labor',
        'additional_acquisition' => 'Additional acquisition cost',
        'other' => 'Other cost',
    ],

    'fields' => [
        'branch' => 'Branch',
        'location' => 'Location',
        'availability' => 'Sale availability',
        'acquisition' => 'Acquisition',
        'outcome' => 'Outcome',
        'grade' => 'Grade',
        'condition' => 'Grade',
        'findings' => 'Findings',
        'repairer_type' => 'Who repairs it',
        'repairer_name' => 'Repair shop or technician',
        'problem' => 'Problem',
        'work_performed' => 'Work performed',
        'close_reason' => 'Reason for cancelling',
        'category' => 'Cost type',
        'description' => 'Description',
        'cost_description' => 'Description',
        'part_description' => 'Parts used',
        'amount' => 'Amount',
        'repair' => 'Related repair',
        'reversal_reason' => 'Reason for correction',
        'correction_reason' => 'Reason for correction',
        'new_value' => 'Corrected value',
    ],

    'location' => [
        'in_store' => 'In the store',
        'in_house' => 'In the store, with the technician',
        'external' => 'At :name (external repair)',
        'removed' => 'No longer in stock',
    ],

    'availability' => [
        'available' => 'Can be sold',
        'unavailable' => 'Not available for sale',
    ],

    'acquisition' => [
        'invoice' => 'Purchase invoice :number',
        'receipt' => 'Received on a purchase invoice',
        'unknown' => 'Unknown — registered without a purchase receipt',
        'opening' => 'Initial stock setup — cost unknown',
        'found' => 'Found during reconciliation — source unverified, cost unknown',
    ],

    'actions' => [
        'label' => 'Device actions',
        'close' => 'Close',
    ],

    'inspection' => [
        'action' => 'Record inspection',
        'title' => 'Record inspection',
        'effect_ready' => ':from → Available. The device becomes sellable stock.',
        'effect_needs_repair' => 'The device stays in inspection (:from) and is not sellable. Start a repair next.',
        'effect_choose' => 'Choose an outcome to see what will change.',
        'keep_grade' => 'Keep current grade (:grade)',
        'choose_grade' => 'Select a grade (currently :grade)',
        'for_parts_hint' => 'A device graded "For parts" cannot be marked ready for sale.',
        'submit' => 'Save inspection',
        'history_title' => 'Inspections',
        'empty' => 'No inspections recorded yet.',
    ],

    'repair' => [
        'action' => 'Start repair',
        'title' => 'Start repair',
        'effect' => 'The device is not available for sale until the repair closes and an inspection marks it ready.',
        'submit' => 'Start repair',
        'history_title' => 'Repairs',
        'empty' => 'No repairs recorded yet.',
        'costing_pending' => 'Costing pending',
        'started' => 'Started by :name',
        'closed' => 'closed by :name',
        'returned_confirmed' => 'Return to store confirmed',
        'confirm_returned' => 'I confirm the device is physically back from :name in store :store.',
    ],

    'complete' => [
        'action' => 'Complete repair',
        'title' => 'Complete repair',
        'effect' => 'It must pass an inspection before it can be sold.',
        'costs' => 'Repair costs',
        'costs_help' => 'Optional. Leave as pending if the amounts are not known yet; they can be recorded later.',
        'costing_pending_note' => 'Repair costs will show as pending until someone with cost access records them.',
        'submit' => 'Complete repair',
    ],

    'cancel' => [
        'action' => 'Cancel repair',
        'title' => 'Cancel repair',
        'effect' => 'No repair work is recorded. It must pass an inspection before it can be sold.',
        'submit' => 'Cancel repair',
    ],

    'cost' => [
        'action' => 'Record cost',
        'title' => 'Record device cost',
        'effect' => 'Adds a permanent cost entry for this device. Status and stock do not change.',
        'mode_pending' => 'Not recorded now (pending)',
        'mode_known' => 'Known amount',
        'mode_unknown' => 'Amount unknown',
        'unknown' => 'Unknown',
        'no_repair' => 'Not linked to a repair',
        'no_stock_effect' => 'Recording a part cost does not take the part out of stock.',
        'submit' => 'Record cost',
    ],

    'reversal' => [
        'action' => 'Correct',
        'title' => 'Correct cost entry',
        'original' => 'Original entry',
        'effect' => 'The original entry stays visible and is marked as reversed. Totals use the replacement, if any.',
        'replacement' => 'Replacement entry',
        'mode_none' => 'No replacement',
        'mode_known' => 'Known amount',
        'mode_unknown' => 'Amount unknown',
        'submit' => 'Save correction',
    ],

    'costs' => [
        'title' => 'Device costs',
        'subtitle' => 'Each currency is totalled separately; amounts are never converted.',
        'acquisition' => 'Acquisition (purchase invoice)',
        'acquisition_restricted' => 'Requires purchasing access',
        'unknown_count' => '{1} plus 1 unknown amount|[2,*] plus :count unknown amounts',
        'recorded_total' => 'Recorded device costs',
        'excludes_unknown' => '{1} Excludes 1 unknown amount|[2,*] Excludes :count unknown amounts',
        'currency_note' => 'TRY and USD are shown separately and never added together.',
        'entries' => 'Cost entries',
        'empty' => 'No device costs recorded.',
        'for_repair' => 'Repair #:id',
        'replaces' => 'Replaces a reversed entry',
        'reversed' => 'Reversed by :name',
    ],

    'correction' => [
        'action' => 'Correct',
        'title' => 'Correct identifier',
        'effect' => 'Fixes a typo in this device\'s identifier. The old value is released and both values stay in the history.',
        'current' => 'Current value',
        'proposed' => 'Corrected value',
        'confirm' => 'I checked the device itself and the corrected value is right.',
        'submit' => 'Save correction',
    ],

    'timeline' => [
        'title' => 'Device timeline',
        'subtitle' => 'Every stock, service, cost, and identity event. Entries are permanent.',
        'recorded' => 'Recorded',
        'reversed_entry' => 'Reversed :category',
    ],

    'stale' => [
        'title' => 'This device changed',
        'body' => 'Another operator changed this device first; it is now ":status". Nothing was saved. Your input is kept. Review the current state, then choose "Continue with current state" before submitting again.',
        'page' => 'This device is now ":status". Your open forms still refer to the earlier state; review it, then choose "Continue with current state".',
        'field' => 'Not saved: the device changed. Review the current state and continue before submitting again.',
        'form_title' => 'This form was opened for an earlier state of the device',
        'form_current' => 'The device is now ":status".',
        'form_repair' => 'Current open repair: #:id ·',
        'form_help' => 'Nothing was saved and your text is kept. Continuing applies this form to the current state; any return confirmation must be given again.',
        'dismiss' => 'Dismiss',
        'refresh' => 'Continue with current state',
    ],

    'lookup' => [
        'title' => 'Find a device',
        'label' => 'IMEI or serial number',
        'placeholder' => 'Scan or type the full IMEI or serial',
        'help_all' => 'Exact match across your stores. Scanning submits automatically.',
        'help_store' => 'Exact match in this store. Scanning submits automatically.',
        'submit' => 'Find',
        'searching' => 'Searching…',
        'failed' => 'The search could not be completed. Try again.',
        'results_for' => 'Results for :code',
        'none' => 'No device with this exact IMEI or serial number.',
        'choose' => ':count devices match. Choose the right one.',
        'historical_title' => 'Previously recorded values',
        'historical_help' => 'This value was corrected away from a device. Open it only if you are sure it is the same device.',
        'historical_badge' => 'Old value',
        'now_recorded_as' => 'now recorded as :value',
    ],

    'errors' => [
        'required' => 'The :attribute field is required.',
        'transition_not_allowed' => 'This action is not possible while the device is ":status".',
        'used_devices_only' => 'Inspection and repair apply only to used devices.',
        'for_parts_not_ready' => 'A device graded "For parts" cannot be marked ready for sale.',
        'repair_already_open' => 'This device already has an open repair.',
        'no_open_repair' => 'This device has no open repair.',
        'return_confirmation_required' => 'Confirm that the device is physically back before closing an external repair.',
        'remove_during_repair' => 'Close the repair before removing this device.',
        'cost_category' => 'Choose a cost type.',
        'cost_currency' => 'Choose TRY or USD.',
        'cost_amount_required' => 'Enter the amount, or choose "Amount unknown".',
        'cost_amount_invalid' => 'Enter an amount between 0.00 and :max with at most two decimals.',
        'cost_not_found' => 'This cost entry no longer exists for this device.',
        'cost_already_reversed' => 'This cost entry was already corrected.',
        'repair_not_found' => 'The selected repair does not belong to this device.',
        'identifier_unchanged' => 'The corrected value is the same as the current value.',
        'correction_confirmation_required' => 'Confirm that you checked the corrected value on the device.',
    ],
];
