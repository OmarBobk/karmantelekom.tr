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
                'name' => 'Featured Products',
                'description' => 'Our most popular items',
                'order' => 1,
                'position' => SectionPosition::MAIN_SLIDER,
                'is_active' => true,
                'scrollable' => true,
            ],
            [
                'name' => 'New Arrivals',
                'description' => 'Latest additions to our catalog',
                'order' => 2,
                'position' => SectionPosition::MAIN_CONTENT,
                'is_active' => true,
                'scrollable' => false,
            ],
            [
                'name' => 'Special Offers',
                'description' => 'Special offers for our customers',
                'order' => 3,
                'position' => SectionPosition::MAIN_CONTENT,
                'is_active' => true,
                'scrollable' => false,
            ],
        ];

        foreach ($sections as $section) {
            $createdSection = Section::create($section);

            $products = Product::where('is_active', true)
                ->inRandomOrder()
                ->limit(6)
                ->get();

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
