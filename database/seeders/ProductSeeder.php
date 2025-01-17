<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\ProductPrice;
use App\Models\Tag;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // Create categories
        $categories = [
            'Electronics' => ['Phones', 'Laptops', 'Accessories'],
            'Fashion' => ['Men', 'Women', 'Kids'],
            'Home' => ['Furniture', 'Decor', 'Kitchen'],
            'Books' => ['Fiction', 'Non-Fiction', 'Educational']
        ];

        foreach ($categories as $mainCategory => $subCategories) {
            $parent = Category::create(['name' => $mainCategory]);
            foreach ($subCategories as $subCategory) {
                Category::create([
                    'name' => $subCategory,
                    'parent_id' => $parent->id
                ]);
            }
        }

        // Create suppliers
        $suppliers = Supplier::factory(5)->create();

        // Create tags first
        $this->call(TagSeeder::class);
        $tags = Tag::all();

        // Create products with related data
        Product::factory(50)->create()->each(function ($product) use ($tags) {
            // Create prices for each product
            ProductPrice::create([
                'product_id' => $product->id,
                'price_type' => 'retail',
                'price' => fake()->randomFloat(2, 10, 1000),
                'currency' => 'TL'
            ]);

            ProductPrice::create([
                'product_id' => $product->id,
                'price_type' => 'retail',
                'price' => fake()->randomFloat(2, 10, 1000),
                'currency' => '$'
            ]);

            // Attach 2-4 random tags to each product
            $product->tags()->attach(
                $tags->random(rand(2, 4))->pluck('id')->toArray()
            );
        });
    }
}
