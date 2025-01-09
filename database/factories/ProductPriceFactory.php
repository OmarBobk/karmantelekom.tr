<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductPrice>
 */
class ProductPriceFactory extends Factory
{
    protected $model = ProductPrice::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'price_type' => $this->faker->randomElement(['wholesale', 'retail']),
            'price' => $this->faker->randomFloat(2, 10, 1000),
            'currency' => 'TRY',
        ];
    }
}
