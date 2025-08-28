<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'shop_id' => \App\Models\Shop::factory(),
            'user_id' => \App\Models\User::factory(),
            'status' => 'pending',
            'total_price' => $this->faker->randomFloat(2, 100, 10000),
            'notes' => $this->faker->sentence(),
        ];
    }
}
