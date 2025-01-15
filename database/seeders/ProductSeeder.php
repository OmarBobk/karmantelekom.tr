<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\ProductPrice;
use App\Models\ProductImage;
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

        // Create products with related data
        Product::factory(50)->create()->each(function ($product) {
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

            // Create sample images for each product
            for ($i = 1; $i <= 3; $i++) {
                ProductImage::create([
                    'product_id' => $product->id,
                    'image_url' => "products/sample-{$i}.jpg",
                    'is_primary' => $i === 1
                ]);
            }
        });
    }
}
