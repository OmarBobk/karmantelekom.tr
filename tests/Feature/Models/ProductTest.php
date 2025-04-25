<?php

namespace Tests\Feature\Models;

use App\Models\Product;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\Tag;
use App\Models\ProductPrice;
use App\Models\ProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

class ProductTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    protected Product $product;
    protected Category $category;
    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        // Create base test data
        $this->category = Category::factory()->create();
        $this->supplier = Supplier::factory()->create();
        $this->product = Product::factory()->create([
            'category_id' => $this->category->id,
            'supplier_id' => $this->supplier->id,
            'is_retail_active' => true,
            'is_wholesale_active' => true,
        ]);
    }

    #[Test]
    public function it_can_create_a_product(): void
    {
        $productData = [
            'name' => 'Test Product',
            'code' => 'TEST-001',
            'description' => 'Test product description',
            'category_id' => $this->category->id,
            'supplier_id' => $this->supplier->id,
            'is_retail_active' => true,
            'is_wholesale_active' => false,
        ];

        $product = Product::create($productData);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Test Product',
            'slug' => 'test-product', // Auto-generated
            'code' => 'TEST-001',
        ]);
    }

    #[Test]
    public function it_automatically_generates_slug_on_creation(): void
    {
        $product = Product::factory()->create(['name' => 'Test Product Name']);
        $this->assertEquals('test-product-name', $product->slug);
    }

    #[Test]
    public function it_generates_unique_slugs_for_duplicate_names(): void
    {
        $firstProduct = Product::factory()->create(['name' => 'Test Product']);
        $secondProduct = Product::factory()->create(['name' => 'Test Product']);

        $this->assertNotEquals($firstProduct->slug, $secondProduct->slug);
        $this->assertStringStartsWith('test-product', $secondProduct->slug);
    }

    #[Test]
    public function it_has_correct_relationships(): void
    {
        // Test category relationship
        $this->assertInstanceOf(Category::class, $this->product->category);
        $this->assertEquals($this->category->id, $this->product->category->id);

        // Test supplier relationship
        $this->assertInstanceOf(Supplier::class, $this->product->supplier);
        $this->assertEquals($this->supplier->id, $this->product->supplier->id);

        // Test prices relationship
        $price = ProductPrice::factory()->create(['product_id' => $this->product->id]);
        $this->assertInstanceOf(ProductPrice::class, $this->product->prices->first());

        // Test images relationship
        $image = ProductImage::factory()->create(['product_id' => $this->product->id]);
        $this->assertInstanceOf(ProductImage::class, $this->product->images->first());

        // Test tags relationship
        $tag = Tag::factory()->create();
        $this->product->tags()->attach($tag->id);
        $this->assertInstanceOf(Tag::class, $this->product->tags->first());
    }

    #[Test]
    public function it_can_scope_visible_products_for_retail_users(): void
    {
        // Create test products
        $visibleProduct = Product::factory()->create(['is_retail_active' => true]);
        $hiddenProduct = Product::factory()->create(['is_retail_active' => false]);

        // Test retail visibility scope
        $visibleProducts = Product::retailActive()->get();
        
        $this->assertTrue($visibleProducts->contains($visibleProduct));
        $this->assertFalse($visibleProducts->contains($hiddenProduct));
    }

    #[Test]
    public function it_can_scope_visible_products_for_wholesale_users(): void
    {
        // Create test products
        $visibleProduct = Product::factory()->create(['is_wholesale_active' => true]);
        $hiddenProduct = Product::factory()->create(['is_wholesale_active' => false]);

        // Test wholesale visibility scope
        $visibleProducts = Product::wholesaleActive()->get();
        
        $this->assertTrue($visibleProducts->contains($visibleProduct));
        $this->assertFalse($visibleProducts->contains($hiddenProduct));
    }

    #[Test]
    public function it_can_get_primary_image_url(): void
    {
        // Test default image
        $this->assertEquals('products/default.jpg', $this->product->primary_image_url);

        // Test with actual image
        $primaryImage = ProductImage::factory()->create([
            'product_id' => $this->product->id,
            'is_primary' => true,
            'image_url' => 'products/test.jpg'
        ]);

        $nonPrimaryImage = ProductImage::factory()->create([
            'product_id' => $this->product->id,
            'is_primary' => false,
            'image_url' => 'products/test2.jpg'
        ]);

        // Refresh the product model
        $this->product->refresh();

        $this->assertEquals('products/test.jpg', $this->product->primary_image_url);
    }

    #[Test]
    public function it_can_get_price(): void
    {
        $price = ProductPrice::factory()->create([
            'product_id' => $this->product->id,
            'base_price' => 100.00,
            'currency_id' => 1 // Assuming 1 is TRY
        ]);

        $this->assertNotNull($this->product->getPrice('TRY'));
        $this->assertEquals(100.00, $this->product->getPrice('TRY')->base_price);
    }

    #[Test]
    public function it_can_check_visibility(): void
    {
        $this->product->is_active = true;
        $this->assertTrue($this->product->isVisibleToCurrentUser());

        $this->product->is_active = false;
        $this->assertFalse($this->product->isVisibleToCurrentUser());
    }

    #[Test]
    public function it_can_handle_soft_deletes(): void
    {
        // Assuming Product uses SoftDeletes
        $product = Product::factory()->create();
        $productId = $product->id;

        $product->delete();

        // Assert the product is soft deleted
        $this->assertSoftDeleted('products', ['id' => $productId]);
        
        // Assert we can still find the product with trashed()
        $this->assertNotNull(Product::withTrashed()->find($productId));
        
        // Assert the product is not in the normal query scope
        $this->assertNull(Product::find($productId));
    }

    #[Test]
    public function it_cascades_deletes_to_related_models(): void
    {
        // Create related models
        $price = ProductPrice::factory()->create(['product_id' => $this->product->id]);
        $image = ProductImage::factory()->create(['product_id' => $this->product->id]);
        $tag = Tag::factory()->create();
        $this->product->tags()->attach($tag->id);

        // Delete the product
        $this->product->delete();

        // Assert related models are deleted or detached
        $this->assertDatabaseMissing('product_prices', ['id' => $price->id]);
        $this->assertDatabaseMissing('product_images', ['id' => $image->id]);
        $this->assertDatabaseMissing('product_tags', [
            'product_id' => $this->product->id,
            'tag_id' => $tag->id
        ]);
    }
} 