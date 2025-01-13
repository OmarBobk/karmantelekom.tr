<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // Create some categories
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

        // Create some suppliers
        $suppliers = [
            'Tech Solutions Inc.',
            'Fashion Hub',
            'Home Essentials',
            'Book World'
        ];

        foreach ($suppliers as $supplierName) {
            Supplier::create([
                'name' => $supplierName,
                'contact_details' => fake()->phoneNumber() . "\n" . fake()->email() . "\n" . fake()->address()
            ]);
        }

        // Get all categories and suppliers for random assignment
        $categoryIds = Category::pluck('id')->toArray();
        $supplierIds = Supplier::pluck('id')->toArray();

        // Create products
        Product::factory()
            ->count(50)
            ->sequence(fn ($sequence) => [
                'category_id' => fake()->randomElement($categoryIds),
                'supplier_id' => fake()->randomElement($supplierIds),
            ])
            ->create();
    }
}
