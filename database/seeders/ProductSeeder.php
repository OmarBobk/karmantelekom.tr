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
        $ar_name = 'فلاش تريكو قميص نسائي رقم 25-2583';
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
            'description'    => "This is the description of the {$name}. It is crafted with high quality materials, designed for everyday comfort, and carefully inspected to ensure durability, style, and a perfect fit for modern wardrobes.",
            'tr_description' => "Bu, {$tr_name} için açıklamadır. Günlük kullanım için konforlu, yüksek kaliteli kumaşlardan üretilmiş, dayanıklılık ve şıklık sunmak üzere özenle hazırlanmış modern bir tasarımdır.",
            'ar_description' => "هذا هو الوصف للمنتج {$ar_name}. تم تصنيعه من مواد عالية الجودة، ومصمم للاستخدام اليومي مع راحة ممتازة، كما أنه يتمتع بمتانة وأناقة ليناسب خزانة الملابس العصرية.",
            'category_id' => Category::whereName('Cotton Clothes')->first()->id,
        ]);

        // Attach Product Images.
        // Create the primary image for the product
        $image = strtolower($code);

        // Add 0-3 additional non-primary images
        for ($i = 1; $i < 4; $i++) {
            ProductImage::create([
                'product_id' => $product->id,
                'image_url' => "products/{$image}-{$i}.png",
                'is_primary' => $i == 1,
            ]);
        }


        $name    = 'Flash Triko 25-2531 shirt';
        $tr_name = 'Flash Triko 25-2531 gömlek';
        $ar_name = 'فلاش تريكو قميص نسائي رقم 25-2531';
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
            'description'    => "This is the description of the {$name}. A versatile piece suitable for different occasions, created with attention to detail to provide comfort, style, and long-lasting wear in every season.",
            'tr_description' => "{$tr_name} ürünü için kapsamlı bir açıklamadır. Farklı kombinlerle uyumlu, her mevsim kullanılabilecek, rahatlık ve tarzı bir araya getiren kaliteli bir seçimdir.",
            'ar_description' => "هذا وصف مفصل للمنتج {$ar_name}. قطعة متعددة الاستخدامات تناسب المناسبات المختلفة، تم تصميمها بعناية لتوفير الراحة والأناقة والجودة العالية مع استخدام طويل الأمد.",
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
                'image_url' => "products/{$image}-{$i}.png",
                'is_primary' => $i == 1,
            ]);
        }


        $name    = 'Flash Triko 25-2606 shirt';
        $tr_name = 'Flash Triko 25-2606 gömlek';
        $ar_name = 'فلاش تريكو قميص نسائي رقم 25-2606';
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
            'description'    => "This is the description of the {$name}. It features a modern cut and a soft touch, making it ideal for daily wear, while maintaining a stylish appearance suitable for both casual and semi-formal outfits.",
            'tr_description' => "Bu açıklama {$tr_name} ürünü içindir. Yumuşak dokusu ve modern kesimiyle günlük kullanıma uygun, hem rahat hem de şık bir görünüm sunan kullanışlı bir parçadır.",
            'ar_description' => "هذا الوصف خاص بالمنتج {$ar_name}. يتميز بقصة عصرية وملمس ناعم، مما يجعله مناسباً للاستخدام اليومي مع الحفاظ على مظهر أنيق يناسب الإطلالات الكاجوال وشبه الرسمية.",
            'category_id' => Category::whereName('Cotton Clothes')->first()->id,
        ]);

        // Attach Product Images.
        // Create the primary image for the product
        $image = strtolower($code);

        // Add 0-3 additional non-primary images
        for ($i = 1; $i < 4; $i++) {
            ProductImage::create([
                'product_id' => $product->id,
                'image_url' => "products/{$image}-{$i}.png",
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
            'description'    => "This is the description of the {$name}. A premium leather piece created with careful craftsmanship, offering a timeless design, comfortable fit, and durable structure for long-term everyday and special occasion use.",
            'tr_description' => "Bu açıklama {$tr_name} içindir. Özenli işçilikle hazırlanmış, zamansız tasarıma sahip, rahat kalıbı ve dayanıklı yapısıyla hem günlük hem de özel gün kullanımı için ideal bir deri üründür.",
            'ar_description' => "هذا الوصف للمنتج {$ar_name}. قطعة جلدية فاخرة تم تصنيعها بعناية، تتميز بتصميم كلاسيكي وخامة متينة، وتوفر راحة في اللبس واستخداماً طويلاً في المناسبات واليوميات.",
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
                'image_url' => "products/{$image}-{$i}.png",
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
            'description'    => "This is the description of the {$name}. Designed for a confident and modern look, this leather jacket provides warmth, durability, and a sleek finish that complements a wide range of outfits and styles.",
            'tr_description' => "Bu açıklama {$tr_name} ürününü anlatır. Modern ve özgüvenli bir görünüm sunan bu deri ceket, sıcaklık, dayanıklılık ve pek çok farklı tarzla uyumlu şık bir bitiş sağlar.",
            'ar_description' => "هذا الوصف للمنتج {$ar_name}. جاكيت جلدي يمنح مظهراً عصرياً وأنيقاً، يوفر الدفء والمتانة، مع تصميم يناسب مجموعة واسعة من التنسيقات والأساليب اليومية.",
            'category_id' => Category::whereName('Leather')->first()->id,
        ]);

        // Attach Product Images.
        // Create the primary image for the product
        $image = strtolower($code);

        // Add 0-3 additional non-primary images
        for ($i = 1; $i < 4; $i++) {
            ProductImage::create([
                'product_id' => $product->id,
                'image_url' => "products/{$image}-{$i}.png",
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
            'description'    => "This is the description of the {$name}. A stylish leather jacket tailored to enhance any outfit, combining comfort, elegance, and reliable quality for regular wear through various seasons and occasions.",
            'tr_description' => "Bu açıklama {$tr_name} ürünü için hazırlanmıştır. Pek çok kombinle uyumlu, konfor ve zarafeti bir arada sunan, farklı mevsim ve ortamlarda rahatlıkla kullanılabilecek kaliteli bir deri cekettir.",
            'ar_description' => "هذا الوصف للمنتج {$ar_name}. جاكيت جلدي أنيق يضيف لمسة مميزة إلى أي إطلالة، يجمع بين الراحة والأناقة والجودة العالية للاستخدام المستمر في مختلف الفصول والمناسبات.",
            'category_id' => Category::whereName('Leather')->first()->id,
        ]);


        // Attach Product Images.
        // Create the primary image for the product
        $image = strtolower($code);

        // Add 0-3 additional non-primary images
        for ($i = 1; $i < 4; $i++) {
            ProductImage::create([
                'product_id' => $product->id,
                'image_url' => "products/{$image}-{$i}.png",
                'is_primary' => $i == 1,
            ]);
        }


        $name    = 'Foo Moisturizing cream';
        $tr_name = 'Foo Nemlendirici krem';
        $ar_name = 'كريم ترطيب';
        $code = '081';
        $product = Product::create([
            'name' => $name,
            'tr_name' => $tr_name,
            'ar_name' => $ar_name,
            'slug' => Str::slug($name),
            'tr_slug' => Str::slug($name),
            'ar_slug' => Str::slug($name),
            'serial' => 'SN-FOO-MOISTURIZER',
            'code' => $code,
            'description'    => "This is the description of the {$name}. A hydrating cream formulated to nourish the skin, support moisture balance, and leave a smooth, soft finish with regular daily use as part of a consistent skincare routine.",
            'tr_description' => "Bu açıklama {$tr_name} ürünü içindir. Cildi nemlendirmek, yumuşaklık kazandırmak ve günlük cilt bakım rutininin bir parçası olarak düzenli kullanımda canlı bir görünüm sağlamak için geliştirilmiş bir kremdir.",
            'ar_description' => "هذا الوصف للمنتج {$ar_name}. كريم ترطيب مصمم لتغذية البشرة ودعم توازن الرطوبة، ويمنح لمسة ناعمة ومظهرًا صحيًا عند استخدامه بانتظام ضمن روتين العناية اليومي.",
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
                'image_url' => "products/{$image}-{$i}.png",
                'is_primary' => $i == 1,
            ]);
        }


        $name    = 'Foo Day Care Cream';
        $tr_name = 'Foo gündüz bakım kremi';
        $ar_name = 'كريم العناية النهارية';
        $code = '082';
        $product = Product::create([
            'name' => $name,
            'tr_name' => $tr_name,
            'ar_name' => $ar_name,
            'slug' => Str::slug($name),
            'tr_slug' => Str::slug($name),
            'ar_slug' => Str::slug($name),
            'serial' => 'SN-FOO-DAY-CARE',
            'code' => $code,
            'description'    => "This is the description of the {$name}. A day care cream created to protect and refresh the skin during the day, helping maintain elasticity, smoothness, and a bright, well-hydrated appearance over time.",
            'tr_description' => "Bu açıklama {$tr_name} için hazırlanmıştır. Gün boyu cildi korumaya, ferahlatmaya ve elastikiyetini desteklemeye yardımcı olan, pürüzsüz ve aydınlık bir görünüm sunan gündüz bakım kremidir.",
            'ar_description' => "هذا الوصف للمنتج {$ar_name}. كريم عناية نهارية يساعد على حماية البشرة طوال اليوم، ويحافظ على مرونتها ونعومتها ويمنحها مظهراً مشرقاً ورطباً مع الاستخدام المستمر.",
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
                'image_url' => "products/{$image}-{$i}.png",
                'is_primary' => $i == 1,
            ]);
        }


        $name    = 'FOO Lip Balm & Blush Cream';
        $tr_name = 'Foo dudak Balmı & Allık kremi';
        $ar_name = 'مرطب الشفاه وكريم الخدود';
        $code = '083';
        $product = Product::create([
            'name' => $name,
            'tr_name' => $tr_name,
            'ar_name' => $ar_name,
            'slug' => Str::slug($name),
            'tr_slug' => Str::slug($name),
            'ar_slug' => Str::slug($name),
            'serial' => 'SN-FOO-LIP-BALM',
            'code' => $code,
            'description'    => "This is the description of the {$name}. A multi-use lip balm and blush cream that adds natural color, softens the lips and cheeks, and blends easily for a fresh, radiant, and healthy looking finish throughout the day.",
            'tr_description' => "Bu açıklama {$tr_name} ürünü içindir. Doğal bir renk veren, dudak ve yanakları yumuşatan, kolay dağılan ve gün boyu taze, canlı ve sağlıklı bir görünüm sağlayan çok amaçlı bir balm ve kremdir.",
            'ar_description' => "هذا الوصف للمنتج {$ar_name}. بلسم شفاه وكريم خدود متعدد الاستخدامات يضيف لوناً طبيعياً، ويلطف الشفاه والخدود، ويمتزج بسهولة ليمنح إطلالة مشرقة وصحية طوال اليوم.",
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
                'image_url' => "products/{$image}-{$i}.png",
                'is_primary' => $i == 1,
            ]);
        }
    }
}
