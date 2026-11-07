<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Models\Customer;
use App\Models\Store;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Add a customer to a store's counter from a name and phone number.
 *
 * A phone number already on file in the store returns that customer unchanged, also when two
 * counters add the same number at once: the store and normalized phone are unique in the
 * database, and the losing insert reads the winner's row. The result's wasRecentlyCreated
 * tells the caller which happened.
 */
class SaveCounterCustomer
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $actor, Store $store, string $name, string $phone): Customer
    {
        Gate::forUser($actor)->authorize('usePos', $store);

        $name = Str::squish($name);
        $phone = Str::trim($phone);
        $errors = [];

        if ($name === '' || mb_strlen($name) > Customer::MAX_NAME_LENGTH) {
            $errors['customer_name'] = __('pos.customer.errors.name_required', ['max' => Customer::MAX_NAME_LENGTH]);
        }

        $normalized = mb_strlen($phone) > Customer::MAX_PHONE_LENGTH ? null : Customer::normalizePhone($phone);

        if ($normalized === null) {
            $errors['customer_phone'] = __('pos.customer.errors.phone_invalid');
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return Customer::query()->firstOrCreate(
            ['store_id' => $store->id, 'phone_normalized' => $normalized],
            ['name' => $name, 'phone' => $phone, 'created_by' => $actor->id],
        );
    }
}
