<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductTag>
 */
class ProductTagFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'tag_id' => Tag::factory(),
        ];
    }
}
