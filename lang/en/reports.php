<?php

return [
    'navigation' => 'Dashboard',
    'title' => 'Operations dashboard',
    'subtitle' => 'Recorded sales and current stock for the branches you can see in full.',
    'branch_subtitle' => 'Recorded sales and current stock for this branch.',
    'all_branches' => 'All branches',

    'range' => [
        'legend' => 'Sales period',
        'from' => 'From',
        'to' => 'To',
        'apply' => 'Show',
        'today' => 'Today',
        'loading' => 'Updating…',
        'invalid_date' => 'Enter real dates for both From and To.',
        'reversed' => 'From must be on or before To.',
    ],

    'sales' => [
        'title' => 'Recorded sales',
        'period' => 'Completed POS sales from :from to :to, Istanbul time.',
        'scope_note' => 'Totals are the recorded sale totals, including the full total of credit sales. Payments received and debt collections are not included.',
        'branch' => 'Branch',
        'count' => 'Sales',
        'total' => 'Recorded total',
        'view_sales' => 'View sales',
        'view_sales_label' => 'View the sales of :branch for this period',
        'open_branch_label' => 'Open the dashboard of :branch',
        'unavailable' => 'Fix the period above to see the sales figures.',
    ],

    'inventory' => [
        'title' => 'Current inventory',
        'note' => 'Stock right now. The sales period does not apply to it.',
        'quantity_on_hand' => 'Quantity on hand',
        'available_units' => 'Available devices',
        'open' => 'Open inventory',
        'open_label' => 'Open the inventory of :branch',
    ],

    'empty_title' => 'No branches to show',
    'empty_body' => 'You can only see branches where you have access to all sales.',
];
