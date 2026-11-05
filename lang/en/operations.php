<?php

return [
    'validation' => [
        'required' => 'The :attribute field is required.',
        'string' => 'The :attribute must be text.',
        'integer' => 'The :attribute must be a whole number.',
        'numeric' => 'The :attribute must be a number.',
        'min' => 'The :attribute must be at least :min.',
        'max' => 'The :attribute may not be greater than :max.',
        'email' => 'The :attribute must be a valid email address.',
        'unique' => 'This :attribute is already in use.',
        'exists' => 'The selected :attribute is invalid.',
        'in' => 'The selected :attribute is invalid.',
        'enum' => 'The selected :attribute is invalid.',
        'boolean' => 'The selected :attribute is invalid.',
    ],
];
