<?php

namespace Database\Seeders;

use App\Models\Section;
use App\Models\Product;
use Illuminate\Database\Seeder;
use App\Enums\SectionPosition;

class SectionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sections = [
            [
                'name' => 'Featured Wholesale Products',
                'description' => 'Our most popular wholesale items',
                'order' => 1,
                'position' => SectionPosition::MAIN_SLIDER,
                'is_active' => true,
                'scrollable' => true,
                'is_wholesale_active' => true,
                'is_retail_active' => false,
            ],
            [
                'name' => 'Featured Retail Products',
                'description' => 'Our most popular retail items',
                'order' => 2,
                'position' => SectionPosition::MAIN_SLIDER,
                'is_active' => true,
                'scrollable' => true,
                'is_wholesale_active' => false,
                'is_retail_active' => true,
            ],
            [
                'name' => 'Wholesale New Arrivals',
                'description' => 'Latest additions to our wholesale catalog',
                'order' => 3,
                'position' => SectionPosition::MAIN_CONTENT,
                'is_active' => true,
                'scrollable' => false,
                'is_wholesale_active' => true,
                'is_retail_active' => false,
            ],
            [
                'name' => 'Retail New Arrivals',
                'description' => 'Latest additions to our retail catalog',
                'order' => 4,
                'position' => SectionPosition::MAIN_CONTENT,
                'is_active' => true,
                'scrollable' => false,
                'is_wholesale_active' => false,
                'is_retail_active' => true,
            ],
            [
                'name' => 'Wholesale Specials',
                'description' => 'Special offers for wholesale customers',
                'order' => 5,
                'position' => SectionPosition::MAIN_CONTENT,
                'is_active' => true,
                'scrollable' => false,
                'is_wholesale_active' => true,
                'is_retail_active' => false,
            ],
            [
                'name' => 'Retail Deals',
                'description' => 'Exclusive deals for retail customers',
                'order' => 6,
                'position' => SectionPosition::MAIN_CONTENT,
                'is_active' => true,
                'scrollable' => false,
                'is_wholesale_active' => false,
                'is_retail_active' => true,
            ],
        ];

        foreach ($sections as $section) {
            $createdSection = Section::create($section);
            
            // Attach products based on section type
            if ($section['is_wholesale_active']) {
                // For wholesale sections
                $products = Product::where('is_wholesale_active', true)
                    ->inRandomOrder()
                    ->limit(6)
                    ->get();
            } else {
                // For retail sections
                $products = Product::where('is_retail_active', true)
                    ->inRandomOrder()
                    ->limit(6)
                    ->get();
            }

            // Attach products with incremental ordering
            if ($products->count() > 0) {
                $ordering = 0;
                foreach ($products as $product) {
                    $createdSection->products()->attach($product->id, [
                        'ordering' => $ordering++
                    ]);
                }
            }
        }
    }
}
