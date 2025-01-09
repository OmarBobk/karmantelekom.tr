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
            'customer_id' => Customer::factory(),
            'salesperson_id' => 1, // Replace with User::factory() if using dynamic users
            'status' => 'Pending',
            'total_price' => $this->faker->randomFloat(2, 100, 10000),
        ];
    }
}
