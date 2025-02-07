<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Section;
use Illuminate\Database\Seeder;

class SectionProductSeeder extends Seeder
{
    public function run(): void
    {
        // Create 3 sections
        Section::factory()
            ->count(9)
            ->sequence(
                [
                    'name' => 'Featured Products',
                    'description' => 'Our most popular items',
                    'order' => 1,
                    'position' => 'main.slider',
                ],
                [
                    'name' => 'New Arrivals',
                    'description' => 'Latest additions to our catalog',
                    'order' => 2,
                    'position' => 'main.content',
                ],
                [
                    'name' => 'Special Offers',
                    'description' => 'Limited time deals',
                    'order' => 3,
                    'position' => 'main.content',
                ],
            )
            ->create()
            ->each(function (Section $section) {
                // Attach 6 random products to each section with incremental ordering
                $products = Product::inRandomOrder()->limit(6)->get();
                $ordering = 0;
                foreach ($products as $product) {
                    $section->products()->attach($product->id, ['ordering' => $ordering++]);
                }
            });
    }
}
