<?php

namespace Tests\Feature\Livewire\Backend\Products;

use App\Livewire\Backend\Products\ProductsComponent;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductPrice;
use App\Models\Supplier;
use App\Models\Tag;
use App\Models\User;
use App\Services\CurrencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ProductsComponentTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    protected User $admin;
    protected Category $category;
    protected Supplier $supplier;
    protected Product $product;
    protected Tag $tag;

    protected function setUp(): void
    {
        parent::setUp();

        // Create fake storage disk for testing
        Storage::fake('public');

        // Create test data
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->category = Category::factory()->create();
        $this->supplier = Supplier::factory()->create();
        $this->tag = Tag::factory()->create();

        $this->product = Product::factory()->create([
            'category_id' => $this->category->id,
            'supplier_id' => $this->supplier->id,
        ]);

        // Mock CurrencyService
        $this->mock(CurrencyService::class, function ($mock) {
            $mock->shouldReceive('getExchangeRate')
                ->andReturn(0.033);
        });

        // Login as admin
        $this->actingAs($this->admin);
    }

    #[Test]
    public function it_can_mount_component(): void
    {
        Livewire::test(ProductsComponent::class)
            ->assertSet('search', '')
            ->assertSet('sortField', 'created_at')
            ->assertSet('sortDirection', 'DESC')
            ->assertSet('status', '')
            ->assertSet('category', '')
            ->assertSet('dateField', '')
            ->assertSet('dateDirection', 'DESC')
            ->assertOk();
    }

    #[Test]
    public function it_can_load_products(): void
    {
        $products = Product::factory(5)->create();

        Livewire::test(ProductsComponent::class)
            ->assertViewHas('products', function ($loadedProducts) use ($products) {
                return $loadedProducts->count() === 5;
            });
    }

    #[Test]
    public function it_can_search_products(): void
    {
        $searchProduct = Product::factory()->create(['name' => 'Searchable Product']);
        $otherProduct = Product::factory()->create(['name' => 'Other Product']);

        Livewire::test(ProductsComponent::class)
            ->set('search', 'Searchable')
            ->assertViewHas('products', function ($products) use ($searchProduct) {
                return $products->contains($searchProduct) && $products->count() === 1;
            });
    }

    #[Test]
    public function it_can_filter_by_category(): void
    {
        $category2 = Category::factory()->create();
        $product1 = Product::factory()->create(['category_id' => $this->category->id]);
        $product2 = Product::factory()->create(['category_id' => $category2->id]);

        Livewire::test(ProductsComponent::class)
            ->set('category', $this->category->id)
            ->assertViewHas('products', function ($products) use ($product1) {
                return $products->contains($product1) && $products->count() === 1;
            });
    }

    #[Test]
    public function it_can_sort_products(): void
    {
        $oldProduct = Product::factory()->create(['created_at' => now()->subDays(5)]);
        $newProduct = Product::factory()->create(['created_at' => now()]);

        Livewire::test(ProductsComponent::class)
            ->set('sortField', 'created_at')
            ->set('sortDirection', 'DESC')
            ->assertViewHas('products', function ($products) use ($newProduct) {
                return $products->first()->id === $newProduct->id;
            });
    }

    #[Test]
    public function it_can_create_product(): void
    {
        $image = UploadedFile::fake()->image('product.jpg');

        Livewire::test(ProductsComponent::class)
            ->set('addForm', [
                'name' => 'New Product',
                'code' => 'NEW-001',
                'description' => 'New product description',
                'category_id' => $this->category->id,
                'supplier_id' => $this->supplier->id,
                'is_retail_active' => true,
                'is_wholesale_active' => true,
                'prices' => [
                    ['price' => '100', 'currency' => 'TRY', 'price_type' => 'retail'],
                    ['price' => '80', 'currency' => 'TRY', 'price_type' => 'wholesale'],
                    ['price' => '5', 'currency' => 'USD', 'price_type' => 'wholesale']
                ],
                'tags' => [$this->tag->id]
            ])
            ->set('newProductImages', [$image])
            ->call('createProduct')
            ->assertHasNoErrors()
            ->assertDispatchedBrowserEvent('notify');

        $this->assertDatabaseHas('products', [
            'name' => 'New Product',
            'code' => 'NEW-001'
        ]);

        $product = Product::where('code', 'NEW-001')->first();
        
        $this->assertCount(3, $product->prices);
        $this->assertCount(1, $product->images);
        $this->assertCount(1, $product->tags);
    }

    #[Test]
    public function it_validates_product_creation(): void
    {
        Livewire::test(ProductsComponent::class)
            ->set('addForm', [
                'name' => '', // Required field
                'code' => '',
                'description' => 'Too short', // Min 10 characters
                'category_id' => '',
                'supplier_id' => '',
                'prices' => [
                    ['price' => '-100', 'currency' => 'TRY', 'price_type' => 'retail'], // Invalid price
                ]
            ])
            ->call('createProduct')
            ->assertHasErrors(['addForm.name', 'addForm.code', 'addForm.description', 'addForm.category_id', 'addForm.supplier_id', 'addForm.prices.0.price']);
    }

    #[Test]
    public function it_can_update_product(): void
    {
        $product = Product::factory()->create();
        $newTag = Tag::factory()->create();

        Livewire::test(ProductsComponent::class)
            ->call('editProduct', $product->id)
            ->set('editForm', [
                'name' => 'Updated Product',
                'slug' => 'updated-product',
                'code' => 'UPD-001',
                'description' => 'Updated product description',
                'category_id' => $this->category->id,
                'supplier_id' => $this->supplier->id,
                'is_retail_active' => true,
                'is_wholesale_active' => true,
                'prices' => [
                    ['price' => '150', 'currency' => 'TRY', 'price_type' => 'retail'],
                    ['price' => '120', 'currency' => 'TRY', 'price_type' => 'wholesale'],
                    ['price' => '7', 'currency' => 'USD', 'price_type' => 'wholesale']
                ],
                'tags' => [$newTag->id]
            ])
            ->call('updateProduct')
            ->assertHasNoErrors()
            ->assertDispatchedBrowserEvent('notify');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Updated Product',
            'code' => 'UPD-001'
        ]);
    }

    #[Test]
    public function it_can_delete_product(): void
    {
        $product = Product::factory()->create();
        $image = ProductImage::factory()->create(['product_id' => $product->id]);
        $price = ProductPrice::factory()->create(['product_id' => $product->id]);

        Livewire::test(ProductsComponent::class)
            ->call('confirmDelete', $product->id)
            ->assertSet('showDeleteModal', true)
            ->assertSet('editingProduct.id', $product->id)
            ->call('deleteProduct')
            ->assertDispatchedBrowserEvent('notify');

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $this->assertDatabaseMissing('product_images', ['id' => $image->id]);
        $this->assertDatabaseMissing('product_prices', ['id' => $price->id]);
    }

    #[Test]
    public function it_can_handle_bulk_actions(): void
    {
        $products = Product::factory(3)->create(['is_retail_active' => false]);

        Livewire::test(ProductsComponent::class)
            ->set('selectedProducts', $products->pluck('id')->map(fn($id) => (string) $id)->toArray())
            ->set('bulkAction', 'activate_retail')
            ->call('processBulkAction')
            ->assertDispatchedBrowserEvent('notify');

        foreach ($products as $product) {
            $this->assertDatabaseHas('products', [
                'id' => $product->id,
                'is_retail_active' => true
            ]);
        }
    }

    #[Test]
    public function it_can_toggle_product_visibility(): void
    {
        $product = Product::factory()->create(['is_retail_active' => false]);

        Livewire::test(ProductsComponent::class)
            ->call('toggleStatus', $product->id, 'retail')
            ->assertDispatchedBrowserEvent('notify');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'is_retail_active' => true
        ]);
    }

    #[Test]
    public function it_can_handle_image_uploads(): void
    {
        $image = UploadedFile::fake()->image('product.jpg');

        Livewire::test(ProductsComponent::class)
            ->set('editingProduct', $this->product)
            ->set('newImages', [$image])
            ->call('updateProduct')
            ->assertHasNoErrors();

        Storage::disk('public')->assertExists('products/' . $image->hashName());
    }

    #[Test]
    public function it_can_set_primary_image(): void
    {
        $image1 = ProductImage::factory()->create([
            'product_id' => $this->product->id,
            'is_primary' => false
        ]);

        $image2 = ProductImage::factory()->create([
            'product_id' => $this->product->id,
            'is_primary' => false
        ]);

        Livewire::test(ProductsComponent::class)
            ->call('editProduct', $this->product->id)
            ->call('setPrimaryImage', $image1->id);

        $this->assertTrue($image1->fresh()->is_primary);
        $this->assertFalse($image2->fresh()->is_primary);
    }

    #[Test]
    public function it_can_remove_image(): void
    {
        $image = ProductImage::factory()->create([
            'product_id' => $this->product->id,
            'image_url' => 'products/test.jpg'
        ]);

        Storage::fake('public');
        Storage::disk('public')->put('products/test.jpg', 'fake image content');

        Livewire::test(ProductsComponent::class)
            ->call('editProduct', $this->product->id)
            ->call('removeImage', $image->id)
            ->assertDispatchedBrowserEvent('notify');

        $this->assertDatabaseMissing('product_images', ['id' => $image->id]);
        Storage::disk('public')->assertMissing('products/test.jpg');
    }

    #[Test]
    public function it_handles_currency_service_errors(): void
    {
        // Mock CurrencyService to throw an exception
        $this->mock(CurrencyService::class, function ($mock) {
            $mock->shouldReceive('getExchangeRate')
                ->andThrow(new \Exception('API Error'));
        });

        Livewire::test(ProductsComponent::class)
            ->assertSet('exchangeRate', 0.033); // Should use fallback rate
    }
} 