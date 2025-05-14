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
            ['name' => 'Clothes', 'tr_name' => 'Kıyafetler', 'ar_name' => 'ملابس', 'status' => true],
            ['name' => 'Self Care', 'tr_name' => 'Kozmetik', 'ar_name' => 'العناية الشخصية', 'status' => true],
            ['name' => 'Home', 'tr_name' => 'Ev', 'ar_name' => 'ادوات منزلية', 'status' => true],
        ];

        foreach ($categories as $category) {
            Category::create([
                'name' => $category['name'],
                'tr_name' => $category['tr_name'],
                'ar_name' => $category['ar_name'],
                'slug' => \Illuminate\Support\Str::slug($category['name']),
                'parent_id' => null,
                'status' => $category['status'],
            ]);
        }

        // Create subcategories
        $electronics = Category::where('name', 'Clothes')->first();
        $clothing = Category::where('name', 'Self Care')->first();
        $home = Category::where('name', 'Home')->first();

        $subcategories = [
            ['name' => 'Leather', 'tr_name' => 'Deri Kıyafetleri', 'ar_name' => 'ملابس جلدية', 'parent_id' => $electronics->id, 'status' => true],
            ['name' => 'Cotton Clothes', 'tr_name' => 'Pamuklu Kıyafetleri', 'ar_name' => 'ملابس قطنية', 'parent_id' => $electronics->id, 'status' => true],
            ['name' => 'Skin Care', 'tr_name' => 'Cilt Bakımı', 'ar_name' => 'العناية بالبشرة', 'parent_id' => $clothing->id, 'status' => true],
            ['name' => 'Dyson', 'tr_name' => 'Dyson', 'ar_name' => 'دايسون', 'parent_id' => $home->id, 'status' => true],
            ['name' => 'Home', 'tr_name' => 'Ev', 'ar_name' => 'المنزل', 'parent_id' => $home->id, 'status' => true],
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
