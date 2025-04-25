<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Currency;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductPrice;
use App\Models\Supplier;
use App\Models\Tag;
use App\Services\CurrencyService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // Create categories
        $categories = [
            'Accessories' => ['Earbuds', 'Speakers', 'Headphones'],
            'Phones' => ['iPhone', 'Samsung', 'Redmi'],
            'Chargers' => ['Cables', 'Adaptor', 'Charger Set'],
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
        $suppliers = Supplier::factory(5)->create()->modelKeys();

        // Create tags first
        $tags = Tag::all();

//
//        // Accessories - Earbuds
//        $name = 'K55 bluetooth Gaming Earbuds Both';
//        $code = 'K55';
//        $product = Product::create([
//            'name' => $name,
//            'slug' => Str::slug($name),
//            'serial' => 'SN-14234432',
//            'code' => $code,
//            'description' => "this is the description of the {$name}",
//            'category_id' => Category::whereName('Accessories')->first()->id,
//            'supplier_id' => array_rand($suppliers),
//            'is_retail_active' => true,
//            'is_wholesale_active' => true,
//        ]);
//
//        $currencyService = app(CurrencyService::class);
//        $tryCurrency = Currency::where('code', 'TRY')->firstOrFail();
//        $usdCurrency = Currency::where('code', 'USD')->firstOrFail();
//
//        // Generate base retail price in TL
//        $retailPrice = 500;
//
//        // Calculate wholesale price (70% of retail)
//        $wholesalePriceTL = 375;
//
//        // Create retail price (TL only)
//        ProductPrice::create([
//            'product_id' => $product->id,
//            'currency_id' => $tryCurrency->id,
//            'price_type' => ProductPrice::TYPE_RETAIL,
//            'base_price' => $retailPrice,
//            'converted_price' => $retailPrice,
//            'is_main_price' => true
//        ]);
//
//        // Create wholesale price in TL
//        ProductPrice::create([
//            'product_id' => $product->id,
//            'currency_id' => $tryCurrency->id,
//            'price_type' => ProductPrice::TYPE_WHOLESALE,
//            'base_price' => $wholesalePriceTL,
//            'converted_price' => $wholesalePriceTL,
//            'is_main_price' => true
//        ]);
//
//        // Create wholesale price in USD (only for wholesale)
//        $wholesalePriceUSD = $currencyService->convertPrice(
//            $wholesalePriceTL,
//            $tryCurrency,
//            $usdCurrency
//        );
//
//        ProductPrice::create([
//            'product_id' => $product->id,
//            'currency_id' => $usdCurrency->id,
//            'price_type' => ProductPrice::TYPE_WHOLESALE,
//            'base_price' => $wholesalePriceTL,
//            'converted_price' => $wholesalePriceUSD,
//            'is_main_price' => false
//        ]);
//
//        // Attach 2-4 random tags to each product
//        $product->tags()->attach(
//            $tags->random(rand(2, 4))->pluck('id')->toArray()
//        );
//
//        // Attach Product Images.
//        // Create the primary image for the product
//        $image = strtolower($code);
//
//        // Add 0-3 additional non-primary images
//        for ($i = 1; $i < 4; $i++) {
//            ProductImage::create([
//                'product_id' => $product->id,
//                'image_url' => "products/{$image}-{$i}.png",
//                'is_primary' => $i == 1,
//            ]);
//        }


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
