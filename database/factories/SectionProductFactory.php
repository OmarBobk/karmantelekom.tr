<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Section;
use App\Models\SectionProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SectionProduct>
 */
class SectionProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'section_id' => Section::factory(),
            'product_id' => Product::factory(),
            'ordering' => fake()->numberBetween(1, 100),
        ];
    }
}
