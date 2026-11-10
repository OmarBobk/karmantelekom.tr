<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Actions\Pos\ReviewCheckout;
use App\Actions\Storefront\ConfirmBranchOrder;
use App\Actions\Storefront\LinkStorefrontProduct;
use App\Actions\Storefront\QuoteBranchOrder;
use App\Actions\Storefront\SetStorefrontChannel;
use App\Actions\Storefront\SetStorefrontDevice;
use App\Actions\Storefront\SetStorefrontQuantity;
use App\Actions\Storefront\StorefrontPricing;
use App\Actions\Storefront\SubmitBranchOrder;
use App\Enums\StorefrontChannel;
use App\Models\Cart;
use App\Models\Category;
use App\Models\CheckoutAttempt;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Shop;
use App\Models\Store;
use App\Models\StorefrontOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * The KT01 counter with its cable and used phone linked to storefront products and published to
 * retail, four cables and the phone offered online, the branch-order flag on, and a shop owner
 * who may order for one shop.
 */
trait InteractsWithBranchOrders
{
    use InteractsWithPos;

    protected Product $cableProduct;

    protected Product $phoneProduct;

    protected User $buyer;

    protected Shop $shop;

    protected function setUpBranchOrders(int $cableStock = 10, int $cablesOnline = 4): void
    {
        $this->setUpCounter($cableStock);
        $this->useEnglishLocale();
        config(['storefront.branch_orders' => true]);
        Role::findOrCreate('shop_owner');

        $category = Category::factory()->create();
        $this->cableProduct = $this->storefrontProduct($category, 'USB-C cable 1m', 'KT-CBL-1', 49.90);
        $this->phoneProduct = $this->storefrontProduct($category, 'iPhone 13 128 GB', 'KT-IP13', 20.00);

        app(SetStorefrontQuantity::class)($this->owner, $this->store, $this->cable->id, $cablesOnline);
        app(SetStorefrontDevice::class)($this->owner, $this->store, $this->phone->id, true);

        foreach ([[$this->cable->id, $this->cableProduct], [$this->usedPhone->id, $this->phoneProduct]] as [$definitionId, $product]) {
            $listing = app(LinkStorefrontProduct::class)($this->owner, $definitionId, $product->id);
            app(SetStorefrontChannel::class)($this->owner, $listing->id, StorefrontChannel::RETAIL, true);
        }

        $this->buyer = User::factory()->create(['name' => 'Buyer'])->assignRole('shop_owner');
        $this->shop = Shop::factory()->create(['owner_id' => $this->buyer->id]);
    }

    /**
     * @param  array<int, int>  $quantities  Quantity by storefront product id.
     */
    protected function fillCart(array $quantities, ?User $buyer = null): Cart
    {
        $cart = Cart::query()->firstOrCreate(['user_id' => ($buyer ?? $this->buyer)->id]);

        foreach ($quantities as $productId => $quantity) {
            $cart->items()->create(['product_id' => $productId, 'price' => 20, 'quantity' => $quantity, 'subtotal' => 20 * $quantity]);
        }

        return $cart;
    }

    /**
     * Submit the buyer's cart for pickup at a branch at the total the quote shows now.
     */
    protected function submit(?Store $store = null, ?string $key = null, ?int $shopId = null, ?User $buyer = null): StorefrontOrder
    {
        $store ??= $this->store;
        $buyer ??= $this->buyer;

        return app(SubmitBranchOrder::class)($buyer, $shopId ?? $this->shop->id, $store->id, $this->quotedTotal($buyer, $store), $key ?? (string) Str::uuid(), null);
    }

    /**
     * The total the checkout shows the buyer now for their cart at the branch; 0 when not offered.
     */
    protected function quotedTotal(User $buyer, Store $store): int
    {
        $quantities = Cart::query()->where('user_id', $buyer->id)->sole()->items()->get()
            ->mapWithKeys(fn ($item): array => [(int) $item->product_id => (int) $item->quantity]);
        $channel = StorefrontPricing::orderChannel($buyer, $quantities->keys());
        $branches = $channel === null ? [] : app(QuoteBranchOrder::class)->branches($quantities, $channel);

        return collect($branches)->firstWhere('store_id', $store->id)['total_minor'] ?? 0;
    }

    /**
     * A pending order for two cables and the phone.
     */
    protected function pendingOrder(int $cables = 2, bool $withPhone = true): StorefrontOrder
    {
        $this->fillCart(array_filter([$this->cableProduct->id => $cables, $this->phoneProduct->id => $withPhone ? 1 : 0]));

        return $this->submit();
    }

    /**
     * @param  array<int, int>|null  $devices  Chosen device by order line id; defaults to the phone.
     */
    protected function confirm(StorefrontOrder $order, ?User $actor = null, ?array $devices = null, mixed $hours = 48, ?Store $store = null): StorefrontOrder
    {
        $devices ??= $order->lines()->get()->filter->tracksUnits()->mapWithKeys(fn ($line): array => [$line->id => $this->phone->id])->all();

        return app(ConfirmBranchOrder::class)($actor ?? $this->owner, $store ?? $this->store, $order->id, $devices, $hours);
    }

    /**
     * Review a counter checkout that collects the order with exactly its lines.
     */
    protected function reviewPickup(StorefrontOrder $order, ?array $cart = null): CheckoutAttempt
    {
        $cart ??= $this->pickupCart($order);

        return app(ReviewCheckout::class)($this->owner, $this->store, $cart, null, null, null, null, null, $order->id);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function pickupCart(StorefrontOrder $order): array
    {
        $cart = [];

        foreach ($order->lines()->get() as $line) {
            $cart += $line->tracksUnits()
                ? $this->phoneLine($line->unit)
                : $this->cableLine((int) $order->lines()->where('product_definition_id', $line->product_definition_id)->sum('quantity'));
        }

        return $cart;
    }

    /**
     * A wholesale tier of the storefront product in TRY.
     */
    protected function wholesaleTier(Product $product, float $price, int $minQty, int $maxQty, bool $active = true): void
    {
        DB::table('wholesale_products')->insert([
            'product_id' => $product->id, 'currency_id' => $this->tryCurrencyId(), 'price' => $price, 'min_qty' => $minQty, 'max_qty' => $maxQty,
            'is_active' => $active, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * Set the storefront product's retail TRY price; 0 means unpriced, as the admin form stores it.
     */
    protected function retailPrice(Product $product, float $price): void
    {
        ProductPrice::query()->where('product_id', $product->id)->where('currency_id', $this->tryCurrencyId())->update(['base_price' => $price, 'converted_price' => $price]);
    }

    protected function tryCurrencyId(): int
    {
        return (int) (DB::table('currencies')->where('code', 'TRY')->value('id')
            ?? DB::table('currencies')->insertGetId(['code' => 'TRY', 'name' => 'Turkish lira', 'symbol' => 'TL', 'is_default' => true, 'created_at' => now(), 'updated_at' => now()]));
    }

    private function storefrontProduct(Category $category, string $name, string $code, float $retailPrice): Product
    {
        $slug = Str::slug($name);
        $product = Product::factory()->create(['name' => $name, 'code' => $code, 'slug' => $slug, 'tr_slug' => $slug.'-tr', 'ar_slug' => $slug.'-ar', 'tr_name' => $name.' TR', 'ar_name' => $name.' AR', 'is_active' => true, 'category_id' => $category->id]);
        ProductPrice::factory()->create(['product_id' => $product->id, 'currency_id' => $this->tryCurrencyId(), 'base_price' => $retailPrice, 'converted_price' => $retailPrice]);

        return $product;
    }
}
