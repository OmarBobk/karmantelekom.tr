<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Models\Customer;
use App\Models\Store;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Look up the store's counter customers: a complete phone number matches exactly, anything
 * else with a letter searches names. Customers of other stores and older customers without a
 * store are never returned.
 */
class FindCounterCustomers
{
    public const LIMIT = 10;

    public const MIN_NAME_LENGTH = 2;

    /**
     * @return Collection<int, Customer>
     *
     * @throws AuthorizationException
     */
    public function __invoke(User $actor, Store $store, string $term): Collection
    {
        Gate::forUser($actor)->authorize('usePos', $store);

        $term = mb_substr(Str::squish($term), 0, 80);
        $query = Customer::query()->where('store_id', $store->id);

        if (preg_match('/\p{L}/u', $term) !== 1) {
            $phone = Customer::normalizePhone($term);

            return $phone === null ? new Collection : $query->where('phone_normalized', $phone)->limit(1)->get();
        }

        if (mb_strlen($term) < self::MIN_NAME_LENGTH) {
            return new Collection;
        }

        return $query
            ->where('name', 'like', '%'.addcslashes($term, '%_\\').'%')
            ->orderBy('name')
            ->orderBy('id')
            ->limit(self::LIMIT)
            ->get();
    }
}
