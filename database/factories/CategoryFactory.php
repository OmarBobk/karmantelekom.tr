<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->word();
        
        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'parent_id' => null, // Can be adjusted for hierarchical seeding
            'status' => $this->faker->boolean(80), // 80% chance of being active
        ];
    }
}
