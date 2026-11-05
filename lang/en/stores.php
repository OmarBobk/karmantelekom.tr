<?php

return [
    'navigation' => 'Stores',
    'title' => 'Stores',
    'subtitle' => 'Manage Karman Telekom operational stores and their assigned employees.',

    'fields' => [
        'code' => 'Store code',
        'name' => 'Store name',
        'phone' => 'Phone',
        'address' => 'Address',
        'employees_count' => 'Employees',
        'employee' => 'Employee',
    ],

    'attributes' => [
        'code' => 'store code',
        'name' => 'store name',
        'phone' => 'phone',
        'address' => 'address',
        'employee' => 'employee',
    ],

    'actions' => [
        'create' => 'Add store',
        'edit' => 'Edit',
        'save' => 'Save',
        'saving' => 'Saving…',
        'cancel' => 'Cancel',
        'view' => 'View details',
        'back' => 'Back to stores',
        'assign' => 'Assign',
        'assigning' => 'Assigning…',
        'remove' => 'Remove',
        'confirm_remove' => 'Yes, remove from store',
    ],

    'form' => [
        'create_title' => 'Add a new store',
        'edit_title' => 'Edit store details',
        'code_help' => 'A permanent store identifier (Latin letters, numbers and dashes). It cannot be changed after creation.',
        'code_locked' => 'The store code is permanent and cannot be changed.',
        'not_provided' => 'Not provided',
    ],

    'empty' => [
        'stores_title' => 'No stores yet',
        'stores_body' => 'Add your first store to start assigning employees.',
        'employees_title' => 'No assigned employees',
        'employees_body' => 'Assign an existing employee to this store from the list above.',
        'no_available_employees' => 'No employees are available to assign. A user must have the "employee" role and not already be assigned to this store.',
    ],

    'employees' => [
        'title' => 'Assigned employees',
        'assign_title' => 'Assign an employee',
        'select_placeholder' => 'Select an employee',
        'other_stores' => 'Other stores',
        'none' => 'None',
    ],

    'remove' => [
        'title' => 'Remove employee from store',
        'body' => ':name will be unassigned from :store only.',
        'account_note' => "The employee's account stays available, and their assignments to other stores are not affected.",
    ],

    'messages' => [
        'created' => 'Store created successfully.',
        'updated' => 'Store details updated successfully.',
        'assigned' => ':name was assigned to the store.',
        'already_assigned' => ':name is already assigned to this store.',
        'removed' => ':name was removed from the store.',
        'already_removed' => ':name is not assigned to this store.',
        'not_employee' => 'Only users with the "employee" role can be assigned.',
    ],

    'validation' => [
        'required' => 'The :attribute field is required.',
        'string' => 'The :attribute must be text.',
        'max' => 'The :attribute may not be longer than :max characters.',
        'alpha_dash' => 'The :attribute may only contain Latin letters, numbers and dashes.',
        'unique' => 'This :attribute is already in use.',
        'integer' => 'The selected :attribute is invalid.',
        'exists' => 'The selected :attribute is invalid.',
    ],
];
