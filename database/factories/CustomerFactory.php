<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name,
            'email' => $this->faker->safeEmail,
            'phone' => $this->faker->phoneNumber,
            'address' => $this->faker->address,
        ];
    }

    /**
     * A counter customer of the store with a Turkish mobile number.
     */
    public function atCounter(Store $store, ?string $phone = null): static
    {
        $phone ??= '05'.$this->faker->unique()->numerify('#########');

        return $this->state(fn (): array => [
            'store_id' => $store->id,
            'email' => null,
            'address' => null,
            'phone' => $phone,
            'phone_normalized' => Customer::normalizePhone($phone),
        ]);
    }
}
