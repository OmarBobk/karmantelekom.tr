<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = $this->faker->words(3, true);
        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'serial' => $this->faker->unique()->numerify('SN-######'),
            'code' => $this->faker->unique()->regexify('[A-Z]{2}[0-9]{6}'),
            'description' => $this->faker->paragraph,
            'category_id' => Category::factory(),
            'supplier_id' => Supplier::factory(),
            'is_retail_active' => false,
            'is_wholesale_active' => false,
        ];
    }
}
