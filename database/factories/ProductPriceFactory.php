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
            'currency_id' => 1, // TRY
            'base_price' => $this->faker->randomFloat(2, 10, 1000),
            'converted_price' => $this->faker->randomFloat(2, 10, 1000),
            'is_main_price' => true
        ];
    }
}
