<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Storefront\CreateStorefrontProduct;
use App\Actions\Storefront\LinkStorefrontProduct;
use App\Actions\Storefront\SetStorefrontChannel;
use App\Actions\Storefront\SetStorefrontDevice;
use App\Actions\Storefront\SetStorefrontQuantity;
use App\Actions\Storefront\StorefrontOrderGuard;
use App\Actions\Storefront\UnlinkStorefrontProduct;
use App\Enums\OrderStatus;
use App\Enums\StorefrontChannel;
use App\Events\OrderCreated;
use App\Livewire\Backend\Storefront\StorefrontPublisher;
use App\Livewire\Frontend\Cart\CartComponent;
use App\Livewire\Frontend\CatalogComponent;
use App\Livewire\Frontend\CheckoutComponent;
use App\Livewire\Frontend\ProductModalComponent;
use App\Livewire\Frontend\ProductsComponent;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductPrice;
use App\Models\Shop;
use App\Models\StorefrontListing;
use App\Models\User;
use App\Models\WholesaleProduct;
use App\Services\CartService;
use Closure;
use Database\Seeders\OperationalStoreSetupSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\Support\InteractsWithPos;
use Tests\TestCase;

class StorefrontPublishingTest extends TestCase
{
    use InteractsWithPos;
    use RefreshDatabase;

    private User $publisher;

    private User $wholesaleBuyer;

    private Category $category;

    private Product $legacyCable;

    private Product $unrelated;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCounter();
        $this->useEnglishLocale();
        Role::create(['name' => 'customer_salesperson']);

        $this->publisher = User::factory()->create(['name' => 'Publisher'])->assignRole('salesperson');
        $this->publisher->givePermissionTo(OperationalStoreSetupSeeder::PUBLISH_STOREFRONT_PERMISSION);
        $this->wholesaleBuyer = User::factory()->create()->assignRole('customer_salesperson');

        $this->category = Category::factory()->create(['slug' => 'cables']);
        $this->legacyCable = $this->storefrontProduct('USB-C cable 1m', 'KT-CBL-1', 'usb-c-cable-1m');
        $this->unrelated = $this->storefrontProduct('Phone case', 'KT-CASE-1', 'phone-case');

        app(SetStorefrontQuantity::class)($this->owner, $this->store, $this->cable->id, 4);
    }

    #[Test]
    public function only_users_with_the_publishing_permission_can_open_the_workspace_and_publish(): void
    {
        $salesperson = User::factory()->create()->assignRole('salesperson');
        $cashier = $this->createCashier();

        foreach ([$salesperson, $cashier] as $denied) {
            Livewire::actingAs($denied)->test(StorefrontPublisher::class)->assertForbidden();
            $this->assertDenied(fn () => $this->link($this->legacyCable, $denied));
        }

        $this->actingAs($salesperson)->get(route('subdomain.storefront'))->assertForbidden();
        $this->actingAs($this->publisher)->get(route('subdomain.storefront'))
            ->assertOk()
            ->assertSee('USB-C cable');
        $this->assertSame(0, StorefrontListing::count());

        $this->assertDenied(fn () => app(SetStorefrontQuantity::class)($this->publisher, $this->store, $this->cable->id, 2));
    }

    #[Test]
    public function linking_keeps_the_existing_product_urls_images_translations_and_order_references(): void
    {
        $order = Order::query()->create(['shop_id' => Shop::factory()->create()->id, 'user_id' => $this->owner->id, 'status' => OrderStatus::PENDING, 'total_price' => '20.00']);
        $item = OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $this->legacyCable->id, 'subtotal' => '20.00']);
        $image = ProductImage::factory()->create(['product_id' => $this->legacyCable->id]);
        $before = $this->legacyCable->fresh()->getRawOriginal();

        $listing = $this->link($this->legacyCable);
        $this->assertSame($listing->id, $this->link($this->legacyCable)->id);
        $this->assertSame(1, $this->auditEvents('listing_linked'));

        $this->assertSame($before, $this->legacyCable->fresh()->getRawOriginal());
        $this->assertSame($this->legacyCable->id, $item->fresh()->product_id);
        $this->assertSame($this->legacyCable->id, $image->fresh()->product_id);
        $this->assertNull($listing->retail_published_at);
        $this->assertNull($listing->wholesale_published_at);

        $this->assertLinkError(fn () => $this->link($this->unrelated), 'already linked to another storefront product');
        $other = app(SetStorefrontDevice::class)($this->owner, $this->store, $this->phone->id, true);
        $this->assertNotNull($other);
        $this->assertLinkError(fn () => app(LinkStorefrontProduct::class)($this->publisher, $this->usedPhone->id, $this->legacyCable->id), 'already linked to another product');

        $this->publish($listing, StorefrontChannel::RETAIL);
        $this->assertLinkError(fn () => app(UnlinkStorefrontProduct::class)($this->publisher, $listing->id), 'Withdraw it from every channel');
        $this->publish($listing, StorefrontChannel::RETAIL, false);
        app(UnlinkStorefrontProduct::class)($this->publisher, $listing->id);
        $this->assertSame(0, StorefrontListing::count());
        $this->assertNotNull($this->legacyCable->fresh());
        $this->assertContains($this->legacyCable->id, $this->visibleIds(StorefrontChannel::RETAIL));
        StorefrontOrderGuard::assertOrderable([$this->legacyCable->id]);
    }

    #[Test]
    public function a_publisher_created_product_stays_hidden_and_unorderable_after_unlinking_and_can_be_relinked(): void
    {
        $listing = app(CreateStorefrontProduct::class)($this->publisher, $this->cable->id, $this->category->id);
        $created = $listing->product;
        ProductPrice::factory()->create(['product_id' => $created->id, 'currency_id' => $this->currencyId(), 'base_price' => 20.00, 'converted_price' => 20.00]);
        $this->publish($listing, StorefrontChannel::RETAIL);
        $this->publish($listing, StorefrontChannel::RETAIL, false);
        app(UnlinkStorefrontProduct::class)($this->publisher, $listing->id);

        $kept = Product::query()->find($created->id);
        $this->assertNotNull($kept);
        $this->assertSame($created->slug, $kept->slug);
        $this->assertTrue($kept->is_active);
        foreach (StorefrontChannel::cases() as $channel) {
            $this->assertFalse(Product::query()->visibleInChannels([$channel])->whereKey($created->id)->exists());
        }
        $this->openModal($this->wholesaleBuyer, $kept)->assertSet('showModal', false);
        $this->assertThrows(fn () => app(CartService::class)->addToCart($this->wholesaleBuyer->id, null, $kept, 1), ValidationException::class);
        $this->assertSame([$created->id], StorefrontOrderGuard::displayOnlyProductIds([$created->id, $this->unrelated->id]));

        $relinked = app(LinkStorefrontProduct::class)($this->publisher, $this->cable->id, $created->id);
        $this->assertSame($created->id, $relinked->product_id);
        $this->assertSame($created->slug, $relinked->product->slug);
    }

    #[Test]
    public function linked_wholesale_tiers_reach_only_wholesale_viewers_of_a_wholesale_listing(): void
    {
        $listing = $this->link($this->legacyCable);
        WholesaleProduct::query()->create(['product_id' => $this->legacyCable->id, 'currency_id' => $this->currencyId(), 'price' => '37.45', 'min_qty' => 6, 'max_qty' => 60, 'is_active' => true]);
        WholesaleProduct::query()->create(['product_id' => $this->unrelated->id, 'currency_id' => $this->currencyId(), 'price' => '11.15', 'min_qty' => 5, 'max_qty' => 50, 'is_active' => true]);
        $this->publish($listing, StorefrontChannel::RETAIL);
        $retailPrice = $this->legacyCable->prices()->firstOrFail()->getFormattedPrice();

        $guest = $this->openModal(null, $this->legacyCable)->assertSet('showModal', true)->assertSee($retailPrice);
        $this->assertNoTiers($guest);
        $this->assertNoTiers($guest->call('selectImage', null, null)->assertSee($retailPrice));
        $this->assertNoTiers($this->openModal($this->wholesaleBuyer, $this->legacyCable)->assertSet('showModal', true));

        $this->publish($listing, StorefrontChannel::WHOLESALE);
        $this->openModal($this->wholesaleBuyer, $this->legacyCable)->assertSee('37.45')->call('selectImage', null, null)->assertSee('37.45');
        $this->assertNoTiers($this->openModal(null, $this->legacyCable)->assertSee($retailPrice));

        $this->openModal(null, $this->unrelated)->assertSee('11.15');
    }

    #[Test]
    public function an_unallocated_product_cannot_be_linked_and_a_new_listing_needs_a_category(): void
    {
        $this->assertLinkError(fn () => app(LinkStorefrontProduct::class)($this->publisher, $this->usedPhone->id, $this->unrelated->id), 'No branch has offered this product online');

        $this->assertLinkError(fn () => app(CreateStorefrontProduct::class)($this->publisher, $this->cable->id, null), 'Choose a storefront category');
        $listing = app(CreateStorefrontProduct::class)($this->publisher, $this->cable->id, $this->category->id);
        $product = $listing->product;
        $this->assertSame('CBL-USBC-1M', $product->code);
        $this->assertTrue($product->is_active);
        $this->assertSame($this->category->id, $product->category_id);
        $this->assertNull($listing->retail_published_at);
        $this->assertSame(1, $this->auditEvents('listing_created'));
    }

    #[Test]
    public function publication_is_per_channel_and_wholesale_requires_active_tiers(): void
    {
        $listing = $this->link($this->legacyCable);
        $this->assertLinkError(fn () => $this->publish($listing, StorefrontChannel::WHOLESALE), 'Add active wholesale price tiers', 'publish');

        WholesaleProduct::query()->create(['product_id' => $this->legacyCable->id, 'currency_id' => $this->currencyId(), 'price' => '30.00', 'min_qty' => 5, 'max_qty' => 50, 'is_active' => true]);
        $this->publish($listing, StorefrontChannel::WHOLESALE);
        $this->publish($listing, StorefrontChannel::WHOLESALE);
        $this->assertSame(1, $this->auditEvents('listing_published'));
        $this->assertTrue($listing->fresh()->isPublishedTo(StorefrontChannel::WHOLESALE));
        $this->assertFalse($listing->fresh()->isPublishedTo(StorefrontChannel::RETAIL));

        $this->legacyCable->update(['is_active' => false]);
        $this->assertLinkError(fn () => $this->publish($listing, StorefrontChannel::RETAIL), 'inactive in the product admin', 'publish');
        $this->legacyCable->update(['is_active' => true]);

        app(SetStorefrontQuantity::class)($this->owner, $this->store, $this->cable->id, 0);
        $this->assertLinkError(fn () => $this->publish($listing, StorefrontChannel::RETAIL), 'No branch has offered this product online', 'publish');
        $this->publish($listing, StorefrontChannel::WHOLESALE, false);
        $this->assertFalse($listing->fresh()->isPublished());
    }

    #[Test]
    public function the_publisher_screen_links_and_publishes_with_its_actions(): void
    {
        Livewire::actingAs($this->publisher)
            ->test(StorefrontPublisher::class)
            ->assertSee('USB-C cable')
            ->call('openLink', $this->cable->id)
            ->set('productSearch', 'KT-CBL')
            ->assertSee('usb-c-cable-1m')
            ->assertDontSee('phone-case')
            ->call('link', $this->legacyCable->id)
            ->assertHasNoErrors()
            ->assertSet('linkingDefinitionId', null);

        $listing = StorefrontListing::query()->sole();

        Livewire::actingAs($this->publisher)
            ->test(StorefrontPublisher::class)
            ->call('setChannel', $listing->id, 'retail', true)
            ->assertHasNoErrors()
            ->call('setChannel', $listing->id, 'wholesale', true)
            ->assertHasErrors('publish');
        $this->assertTrue($listing->fresh()->isPublishedTo(StorefrontChannel::RETAIL));
    }

    #[Test]
    public function linked_products_show_only_in_published_channels_while_legacy_products_keep_their_rules(): void
    {
        $listing = $this->link($this->legacyCable);
        $tier = WholesaleProduct::query()->create(['product_id' => $this->legacyCable->id, 'currency_id' => $this->currencyId(), 'price' => '30.00', 'min_qty' => 5, 'max_qty' => 50, 'is_active' => true]);
        WholesaleProduct::query()->create(['product_id' => $this->unrelated->id, 'currency_id' => $this->currencyId(), 'price' => '10.00', 'min_qty' => 5, 'max_qty' => 50, 'is_active' => true]);

        $this->assertEqualsCanonicalizing([$this->unrelated->id], $this->visibleIds(StorefrontChannel::RETAIL));
        $this->assertEqualsCanonicalizing([$this->unrelated->id], $this->visibleIds(StorefrontChannel::WHOLESALE));
        $this->openModal(null, $this->legacyCable)->assertSet('showModal', false)->assertDispatched('notify');

        $this->publish($listing, StorefrontChannel::WHOLESALE);
        $this->assertEqualsCanonicalizing([$this->unrelated->id], $this->visibleIds(StorefrontChannel::RETAIL));
        $this->assertEqualsCanonicalizing([$this->legacyCable->id, $this->unrelated->id], $this->visibleIds(StorefrontChannel::WHOLESALE));

        Livewire::test(ProductsComponent::class, ['category' => 'all'])
            ->assertSee('Phone case')
            ->assertDontSee('USB-C cable 1m');
        Livewire::actingAs($this->wholesaleBuyer)
            ->test(CatalogComponent::class, ['category' => 'all'])
            ->assertSee('USB-C cable 1m')
            ->assertSee('Phone case');

        $this->openModal(null, $this->legacyCable)->assertSet('showModal', false);
        $this->openModal($this->wholesaleBuyer, $this->legacyCable)->assertSet('showModal', true)->assertSet('storefrontAvailable', 4);
        $this->openModal(null, $this->unrelated)->assertSet('showModal', true)->assertSet('storefrontAvailable', null);

        $this->unrelated->update(['is_active' => false]);
        $this->openModal(null, $this->unrelated)->assertSet('showModal', true);

        $this->publish($listing, StorefrontChannel::RETAIL);
        $this->assertContains($this->legacyCable->id, $this->visibleIds(StorefrontChannel::RETAIL));
        $this->openModal(null, $this->legacyCable)->assertSet('showModal', true)->assertSee('4 in stock');
        $this->assertNotNull($tier->fresh());
    }

    #[Test]
    public function retail_and_wholesale_show_the_same_live_availability_without_internal_details(): void
    {
        $listing = $this->link($this->legacyCable);
        WholesaleProduct::query()->create(['product_id' => $this->legacyCable->id, 'currency_id' => $this->currencyId(), 'price' => '30.00', 'min_qty' => 5, 'max_qty' => 50, 'is_active' => true]);
        $this->publish($listing, StorefrontChannel::RETAIL);
        $this->publish($listing, StorefrontChannel::WHOLESALE);

        $retail = Livewire::test(ProductsComponent::class, ['category' => 'all'])->assertSee('4 in stock');
        Livewire::actingAs($this->wholesaleBuyer)->test(CatalogComponent::class, ['category' => 'all'])->assertSee('4 in stock');

        $html = $retail->html();
        foreach (['KT01', 'CBL-USBC-1M', 'product_definition_id', 'inventory_unit_id', 'unit_cost', 'storefront_listing', $this->phoneImei] as $internal) {
            $this->assertStringNotContainsString($internal, $html);
        }

        $cashier = $this->createCashier(name: 'Fatih cashier');
        $this->finalize($cashier, $this->review($cashier, $this->cableLine(8)), '399.20');
        Livewire::test(ProductsComponent::class, ['category' => 'all'])->assertSee('2 in stock');
        Livewire::actingAs($this->wholesaleBuyer)->test(CatalogComponent::class, ['category' => 'all'])->assertSee('2 in stock');

        $this->finalize($cashier, $this->review($cashier, $this->cableLine(2)), '99.80');
        Livewire::test(ProductsComponent::class, ['category' => 'all'])->assertSee('Out of stock');
    }

    #[Test]
    public function linked_branch_stock_cannot_be_ordered_from_any_cart_path(): void
    {
        Event::fake([OrderCreated::class]);
        $buyer = User::factory()->create();
        $cart = Cart::query()->create(['user_id' => $buyer->id]);
        $cart->items()->create(['product_id' => $this->legacyCable->id, 'price' => 20, 'quantity' => 1, 'subtotal' => 20]);
        $cart->items()->create(['product_id' => $this->unrelated->id, 'price' => 20, 'quantity' => 1, 'subtotal' => 20]);
        $this->publish($this->link($this->legacyCable), StorefrontChannel::RETAIL);
        $carts = app(CartService::class);

        $this->assertThrows(fn () => $carts->addToCart($buyer->id, null, $this->legacyCable->fresh(), 1), ValidationException::class);

        $returned = $carts->syncCart($buyer->id, [['product_id' => $this->legacyCable->id, 'quantity' => 3], ['product_id' => $this->unrelated->id, 'quantity' => 2]]);
        $this->assertSame([$this->unrelated->id], array_column($returned, 'product_id'));
        $this->assertSame([$this->unrelated->id], $cart->items()->pluck('product_id')->all());

        Livewire::actingAs($buyer)
            ->test(CartComponent::class)
            ->call('handleOrderNow', [['product_id' => $this->legacyCable->id, 'quantity' => 1]], 20)
            ->assertHasErrors('order');
        $this->assertSame(0, Order::count());

        $cart->items()->create(['product_id' => $this->legacyCable->id, 'price' => 20, 'quantity' => 1, 'subtotal' => 20]);
        $shop = Shop::factory()->create();
        Livewire::actingAs($buyer)
            ->test(CheckoutComponent::class)
            ->set('selectedShopId', $shop->id)
            ->call('placeOrder')
            ->assertHasErrors('cart');
        $this->assertSame(0, Order::count());
        $this->assertSame([$this->unrelated->id], $cart->items()->pluck('product_id')->all());

        Livewire::actingAs($buyer)
            ->test(CheckoutComponent::class)
            ->set('selectedShopId', $shop->id)
            ->call('placeOrder')
            ->assertHasNoErrors();
        $this->assertSame([$this->unrelated->id], Order::query()->sole()->items()->pluck('product_id')->all());
    }

    private function storefrontProduct(string $name, string $code, string $slug): Product
    {
        $product = Product::factory()->create(['name' => $name, 'code' => $code, 'slug' => $slug, 'tr_slug' => $slug.'-tr', 'ar_slug' => $slug.'-ar', 'tr_name' => $name.' TR', 'ar_name' => $name.' AR', 'is_active' => true, 'category_id' => $this->category->id]);
        ProductPrice::factory()->create(['product_id' => $product->id, 'currency_id' => $this->currencyId(), 'base_price' => 20.00, 'converted_price' => 20.00]);

        return $product;
    }

    private function link(Product $product, ?User $actor = null): StorefrontListing
    {
        return app(LinkStorefrontProduct::class)($actor ?? $this->publisher, $this->cable->id, $product->id);
    }

    private function publish(StorefrontListing $listing, StorefrontChannel $channel, bool $publish = true): StorefrontListing
    {
        return app(SetStorefrontChannel::class)($this->publisher, $listing->id, $channel, $publish);
    }

    /**
     * @return list<int>
     */
    private function visibleIds(StorefrontChannel $channel): array
    {
        return Product::query()->visibleInChannels([$channel])->whereIn('id', [$this->legacyCable->id, $this->unrelated->id])->pluck('id')->all();
    }

    private function openModal(?User $viewer, Product $product): Testable
    {
        if ($viewer === null) {
            Auth::forgetUser();
        }

        $component = $viewer === null ? Livewire::test(ProductModalComponent::class) : Livewire::actingAs($viewer)->test(ProductModalComponent::class);

        return $component->call('openProductModal', $product->id);
    }

    private function assertNoTiers(Testable $modal): void
    {
        $rendered = $modal->html().json_encode($modal->snapshot);

        foreach (['37.45', 'priceTiers', 'wholesalePrices', 'min_qty'] as $tierData) {
            $this->assertStringNotContainsString($tierData, $rendered);
        }
    }

    private function currencyId(): int
    {
        return (int) (DB::table('currencies')->value('id') ?? DB::table('currencies')->insertGetId(['code' => 'TRY', 'name' => 'Turkish lira', 'symbol' => 'TL', 'is_default' => true, 'created_at' => now(), 'updated_at' => now()]));
    }

    private function auditEvents(string $event): int
    {
        return Activity::query()->where('log_name', 'storefront')->where('event', $event)->count();
    }

    private function assertLinkError(Closure $action, string $message, ?string $key = null): void
    {
        try {
            $action();
        } catch (ValidationException $exception) {
            $errors = $key === null ? collect($exception->errors())->flatten() : collect($exception->errors()[$key] ?? []);
            $this->assertTrue($errors->contains(fn (string $error): bool => str_contains($error, $message)), 'Unexpected errors: '.json_encode($exception->errors()));

            return;
        }

        $this->fail("Expected a validation error containing: {$message}");
    }

    /**
     * @param  class-string<\Throwable>  $exception
     */
    private function assertDenied(Closure $action, string $exception = AuthorizationException::class): void
    {
        try {
            $action();
        } catch (\Throwable $thrown) {
            $this->assertInstanceOf($exception, $thrown);

            return;
        }

        $this->fail("Expected {$exception}");
    }
}
