<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Currency;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductPrice;
use App\Models\Tag;
use App\Services\CurrencyService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // Create tags first
        $tags = Tag::all();

        // Clothes - Cotton
        $name    = 'Flash Triko 25-2583 shirt';
        $tr_name = 'Flash Triko 25-2583 gömlek';
        $ar_name = 'ريكو قميص نسائي رقم 25-2583فلاش ت';
        $code = '25-2583';
        $product = Product::create([
            'name' => $name,
            'tr_name' => $tr_name,
            'ar_name' => $ar_name,
            'slug' => Str::slug($name),
            'tr_slug' => Str::slug($name),
            'ar_slug' => Str::slug($name),
            'serial' => 'SN-25-2583',
            'code' => $code,
            'description'    => "this is the description of the {$name}",
            'tr_description' => "ürün açıklaması {$tr_name}",
            'ar_description' => " وصف المنتج{$ar_name}",
            'category_id' => Category::whereName('Cotton Clothes')->first()->id,
        ]);

        // Attach 2-4 random tags to each product
        $product->tags()->attach(
            $tags->random(rand(2, 4))->pluck('id')->toArray()
        );

        // Attach Product Images.
        // Create the primary image for the product
        $image = strtolower($code);

        // Add 0-3 additional non-primary images
        for ($i = 1; $i < 4; $i++) {
            ProductImage::create([
                'product_id' => $product->id,
                'image_url' => "products/{$image}-{$i}.jpg",
                'is_primary' => $i == 1,
            ]);
        }


        $name    = 'Flash Triko 25-2531 shirt';
        $tr_name = 'Flash Triko 25-2531 gömlek';
        $ar_name = 'ريكو قميص نسائي رقم 25-2531فلاش ت';
        $code = '25-2531';
        $product = Product::create([
            'name' => $name,
            'tr_name' => $tr_name,
            'ar_name' => $ar_name,
            'slug' => Str::slug($name),
            'tr_slug' => Str::slug($name),
            'ar_slug' => Str::slug($name),
            'serial' => 'SN-25-2531',
            'code' => $code,
            'description'    => "this is the description of the {$name}",
            'tr_description' => "ürün açıklaması {$tr_name}",
            'ar_description' => " وصف المنتج{$ar_name}",
            'category_id' => Category::whereName('Cotton Clothes')->first()->id,
        ]);

        // Attach 2-4 random tags to each product
        $product->tags()->attach(
            $tags->random(rand(2, 4))->pluck('id')->toArray()
        );

        // Attach Product Images.
        // Create the primary image for the product
        $image = strtolower($code);

        // Add 0-3 additional non-primary images
        for ($i = 1; $i < 4; $i++) {
            ProductImage::create([
                'product_id' => $product->id,
                'image_url' => "products/{$image}-{$i}.jpg",
                'is_primary' => $i == 1,
            ]);
        }


        $name    = 'Flash Triko 25-2606 shirt';
        $tr_name = 'Flash Triko 25-2606 gömlek';
        $ar_name = 'ريكو قميص نسائي رقم 25-2606فلاش ت';
        $code = '25-2606';
        $product = Product::create([
            'name' => $name,
            'tr_name' => $tr_name,
            'ar_name' => $ar_name,
            'slug' => Str::slug($name),
            'tr_slug' => Str::slug($name),
            'ar_slug' => Str::slug($name),
            'serial' => 'SN-25-2606',
            'code' => $code,
            'description'    => "this is the description of the {$name}",
            'tr_description' => "ürün açıklaması {$tr_name}",
            'ar_description' => " وصف المنتج{$ar_name}",
            'category_id' => Category::whereName('Cotton Clothes')->first()->id,
        ]);

        // Attach 2-4 random tags to each product
        $product->tags()->attach(
            $tags->random(rand(2, 4))->pluck('id')->toArray()
        );

        // Attach Product Images.
        // Create the primary image for the product
        $image = strtolower($code);

        // Add 0-3 additional non-primary images
        for ($i = 1; $i < 4; $i++) {
            ProductImage::create([
                'product_id' => $product->id,
                'image_url' => "products/{$image}-{$i}.jpg",
                'is_primary' => $i == 1,
            ]);
        }


        $name    = 'Women\'s leather jacket 078';
        $tr_name = 'Kadın deri ceket 078';
        $ar_name = 'جاكيت جلد نسائي';
        $code = '078';
        $product = Product::create([
            'name' => $name,
            'tr_name' => $tr_name,
            'ar_name' => $ar_name,
            'slug' => Str::slug($name),
            'tr_slug' => Str::slug($name),
            'ar_slug' => Str::slug($name),
            'serial' => 'SN-078',
            'code' => $code,
            'description'    => "this is the description of the {$name}",
            'tr_description' => "ürün açıklaması {$tr_name}",
            'ar_description' => " وصف المنتج{$ar_name}",
            'category_id' => Category::whereName('Leather')->first()->id,
        ]);

        // Attach 2-4 random tags to each product
        $product->tags()->attach(
            $tags->random(rand(2, 4))->pluck('id')->toArray()
        );

        // Attach Product Images.
        // Create the primary image for the product
        $image = strtolower($code);

        // Add 0-3 additional non-primary images
        for ($i = 1; $i < 4; $i++) {
            ProductImage::create([
                'product_id' => $product->id,
                'image_url' => "products/{$image}-{$i}.jpg",
                'is_primary' => $i == 1,
            ]);
        }


        $name    = 'Men\'s leather jacket 079';
        $tr_name = 'Erkek deri ceket 079';
        $ar_name = 'جاكيت جلد رجالي';
        $code = '079';
        $product = Product::create([
            'name' => $name,
            'tr_name' => $tr_name,
            'ar_name' => $ar_name,
            'slug' => Str::slug($name),
            'tr_slug' => Str::slug($name),
            'ar_slug' => Str::slug($name),
            'serial' => 'SN-079',
            'code' => $code,
            'description'    => "this is the description of the {$name}",
            'tr_description' => "ürün açıklaması {$tr_name}",
            'ar_description' => " وصف المنتج{$ar_name}",
            'category_id' => Category::whereName('Leather')->first()->id,
        ]);

        // Attach 2-4 random tags to each product
        $product->tags()->attach(
            $tags->random(rand(2, 4))->pluck('id')->toArray()
        );

        // Attach Product Images.
        // Create the primary image for the product
        $image = strtolower($code);

        // Add 0-3 additional non-primary images
        for ($i = 1; $i < 4; $i++) {
            ProductImage::create([
                'product_id' => $product->id,
                'image_url' => "products/{$image}-{$i}.jpg",
                'is_primary' => $i == 1,
            ]);
        }


        $name    = 'Women\'s leather jacket 080';
        $tr_name = 'Kadın deri ceket 080';
        $ar_name = 'جاكيت جلد نسائي';
        $code = '080';
        $product = Product::create([
            'name' => $name,
            'tr_name' => $tr_name,
            'ar_name' => $ar_name,
            'slug' => Str::slug($name),
            'tr_slug' => Str::slug($name),
            'ar_slug' => Str::slug($name),
            'serial' => 'SN-080',
            'code' => $code,
            'description'    => "this is the description of the {$name}",
            'tr_description' => "ürün açıklaması {$tr_name}",
            'ar_description' => " وصف المنتج{$ar_name}",
            'category_id' => Category::whereName('Leather')->first()->id,
        ]);

        // Attach 2-4 random tags to each product
        $product->tags()->attach(
            $tags->random(rand(2, 4))->pluck('id')->toArray()
        );

        // Attach Product Images.
        // Create the primary image for the product
        $image = strtolower($code);

        // Add 0-3 additional non-primary images
        for ($i = 1; $i < 4; $i++) {
            ProductImage::create([
                'product_id' => $product->id,
                'image_url' => "products/{$image}-{$i}.jpg",
                'is_primary' => $i == 1,
            ]);
        }


        $name    = 'Foo Moisturizing cream';
        $tr_name = 'Foo Nemlendirici krem';
        $ar_name = 'كريم ترطيب';
        $code = 'FOO-MOISTURIZER';
        $product = Product::create([
            'name' => $name,
            'tr_name' => $tr_name,
            'ar_name' => $ar_name,
            'slug' => Str::slug($name),
            'tr_slug' => Str::slug($name),
            'ar_slug' => Str::slug($name),
            'serial' => 'SN-FOO-MOISTURIZER',
            'code' => $code,
            'description'    => "this is the description of the {$name}",
            'tr_description' => "ürün açıklaması {$tr_name}",
            'ar_description' => " وصف المنتج{$ar_name}",
            'category_id' => Category::whereName('Skin Care')->first()->id,
        ]);

        // Attach 2-4 random tags to each product
        $product->tags()->attach(
            $tags->random(rand(2, 4))->pluck('id')->toArray()
        );

        // Attach Product Images.
        // Create the primary image for the product
        $image = strtolower($code);

        // Add 0-3 additional non-primary images
        for ($i = 1; $i < 2; $i++) {
            ProductImage::create([
                'product_id' => $product->id,
                'image_url' => "products/{$image}-{$i}.jpg",
                'is_primary' => $i == 1,
            ]);
        }


        $name    = 'Foo Day Care Cream';
        $tr_name = 'Foo gündüz bakım kremi';
        $ar_name = 'كريم العناية النهارية';
        $code = 'FOO-DAY-CARE';
        $product = Product::create([
            'name' => $name,
            'tr_name' => $tr_name,
            'ar_name' => $ar_name,
            'slug' => Str::slug($name),
            'tr_slug' => Str::slug($name),
            'ar_slug' => Str::slug($name),
            'serial' => 'SN-FOO-DAY-CARE',
            'code' => $code,
            'description'    => "this is the description of the {$name}",
            'tr_description' => "ürün açıklaması {$tr_name}",
            'ar_description' => " وصف المنتج{$ar_name}",
            'category_id' => Category::whereName('Skin Care')->first()->id,
        ]);

        // Attach 2-4 random tags to each product
        $product->tags()->attach(
            $tags->random(rand(2, 4))->pluck('id')->toArray()
        );

        // Attach Product Images.
        // Create the primary image for the product
        $image = strtolower($code);

        // Add 0-3 additional non-primary images
        for ($i = 1; $i < 2; $i++) {
            ProductImage::create([
                'product_id' => $product->id,
                'image_url' => "products/{$image}-{$i}.jpg",
                'is_primary' => $i == 1,
            ]);
        }


        $name    = 'FOO Lip Balm & Blush Cream';
        $tr_name = 'Foo dudak Balmı & Allık kremi';
        $ar_name = 'مرطب الشفاه وكريم الخدود';
        $code = 'FOO-LIP-BALM';
        $product = Product::create([
            'name' => $name,
            'tr_name' => $tr_name,
            'ar_name' => $ar_name,
            'slug' => Str::slug($name),
            'tr_slug' => Str::slug($name),
            'ar_slug' => Str::slug($name),
            'serial' => 'SN-FOO-LIP-BALM',
            'code' => $code,
            'description'    => "this is the description of the {$name}",
            'tr_description' => "ürün açıklaması {$tr_name}",
            'ar_description' => " وصف المنتج{$ar_name}",
            'category_id' => Category::whereName('Skin Care')->first()->id,
        ]);

        // Attach 2-4 random tags to each product
        $product->tags()->attach(
            $tags->random(rand(2, 4))->pluck('id')->toArray()
        );

        // Attach Product Images.
        // Create the primary image for the product
        $image = strtolower($code);

        // Add 0-3 additional non-primary images
        for ($i = 1; $i < 2; $i++) {
            ProductImage::create([
                'product_id' => $product->id,
                'image_url' => "products/{$image}-{$i}.jpg",
                'is_primary' => $i == 1,
            ]);
        }
    }
}
