<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Supplier;
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
        $tags = Tag::all();

        // Create products with related data
        Product::factory(50)->create()->each(function ($product) use ($tags) {
            // Attach 2-4 random tags to each product
            $product->tags()->attach(
                $tags->random(rand(2, 4))->pluck('id')->toArray()
            );

            // Generate a random number between 1 and 6 for the image
            $imageNumber = rand(1, 6);

            // Create the primary image for the product
            ProductImage::create([
                'product_id' => $product->id,
                'image_url' => "products/product-{$imageNumber}.png",
                'is_primary' => true,
            ]);

            // Add 0-3 additional non-primary images
            $additionalImagesCount = rand(0, 3);
            for ($i = 0; $i < $additionalImagesCount; $i++) {
                $additionalImageNumber = rand(1, 6);
                ProductImage::create([
                    'product_id' => $product->id,
                    'image_url' => "products/product-{$additionalImageNumber}.png",
                    'is_primary' => false,
                ]);
            }
        });
    }
}
