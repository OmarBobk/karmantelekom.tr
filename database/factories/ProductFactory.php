<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'sku' => $this->faker->unique()->regexify('[A-Z]{2}[0-9]{6}'),
            'description' => $this->faker->paragraph,
            'category_id' => Category::factory(),
            'supplier_id' => Supplier::factory(),
            'price' => $this->faker->randomFloat(2, 10, 1000),
            'stock' => $this->faker->numberBetween(0, 100),
            'status' => $this->faker->randomElement(['active', 'inactive']),
            'image_url' => $this->faker->imageUrl(400, 400, 'products', true),
        ];
    }
}
