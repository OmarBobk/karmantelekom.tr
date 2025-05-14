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
                'tr_name' => 'Öne Çıkan Ürünler',
                'ar_name' => 'المنتجات المميزة',
                'description' => 'Our most popular items',
                'order' => 1,
                'position' => SectionPosition::MAIN_SLIDER,
                'is_active' => true,
                'scrollable' => true,
            ],
            [
                'name' => 'Top Rated',
                'tr_name' => 'En İyisi',
                'ar_name' => 'الأعلى تقييمًا',
                'description' => 'Our most popular items',
                'order' => 2,
                'position' => SectionPosition::MAIN_SLIDER,
                'is_active' => true,
                'scrollable' => true,
            ],
            [
                'name' => 'Trending Now',
                'tr_name' => 'Trendler',
                'ar_name' => 'الأكثر رواجًا الآن',
                'description' => 'Our most popular items',
                'order' => 3,
                'position' => SectionPosition::MAIN_SLIDER,
                'is_active' => true,
                'scrollable' => true,
            ],
            [
                'name' => 'New This Month',
                'tr_name' => 'Bu Ay Yenilikler',
                'ar_name' => 'جديد هذا الشهر',
                'description' => 'Our most popular items',
                'order' => 4,
                'position' => SectionPosition::MAIN_SLIDER,
                'is_active' => true,
                'scrollable' => true,
            ],
            [
                'name' => 'New Arrivals',
                'tr_name' => 'Yeni Gelenler',
                'ar_name' => 'المنتجات الجديدة',
                'description' => 'Latest additions to our catalog',
                'order' => 5,
                'position' => SectionPosition::MAIN_CONTENT,
                'is_active' => true,
                'scrollable' => false,
            ],
            [
                'name' => 'Special Offers',
                'tr_name' => 'Özel Teklifler',
                'ar_name' => 'عروض خاصة',
                'description' => 'Special offers for our customers',
                'order' => 6,
                'position' => SectionPosition::MAIN_CONTENT,
                'is_active' => true,
                'scrollable' => false,
            ],
            [
                'name' => 'Daily Deals',
                'tr_name' => 'Günlük Fırsatlar',
                'ar_name' => 'العروض اليومية',
                'description' => 'Special offers for our customers',
                'order' => 7,
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
