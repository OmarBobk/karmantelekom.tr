<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create main categories
        $categories = [
            ['name' => 'Electronics', 'status' => true],
            ['name' => 'Clothing', 'status' => true],
            ['name' => 'Home & Kitchen', 'status' => true],
            ['name' => 'Books', 'status' => true],
            ['name' => 'Sports', 'status' => true],
        ];

        foreach ($categories as $category) {
            Category::create([
                'name' => $category['name'],
                'slug' => \Illuminate\Support\Str::slug($category['name']),
                'parent_id' => null,
                'status' => $category['status'],
            ]);
        }

        // Create subcategories
        $electronics = Category::where('name', 'Electronics')->first();
        $clothing = Category::where('name', 'Clothing')->first();
        $home = Category::where('name', 'Home & Kitchen')->first();

        $subcategories = [
            ['name' => 'Smartphones', 'parent_id' => $electronics->id, 'status' => true],
            ['name' => 'Laptops', 'parent_id' => $electronics->id, 'status' => true],
            ['name' => 'Men\'s Clothing', 'parent_id' => $clothing->id, 'status' => true],
            ['name' => 'Women\'s Clothing', 'parent_id' => $clothing->id, 'status' => true],
            ['name' => 'Furniture', 'parent_id' => $home->id, 'status' => true],
            ['name' => 'Kitchen Appliances', 'parent_id' => $home->id, 'status' => true],
        ];

        foreach ($subcategories as $subcategory) {
            Category::create([
                'name' => $subcategory['name'],
                'slug' => \Illuminate\Support\Str::slug($subcategory['name']),
                'parent_id' => $subcategory['parent_id'],
                'status' => $subcategory['status'],
            ]);
        }

        // Create additional random categories
        Category::factory(10)->create();
    }
}
