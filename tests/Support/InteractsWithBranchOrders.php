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
        $this->cableProduct = $this->storefrontProduct($category, 'USB-C cable 1m', 'KT-CBL-1');
        $this->phoneProduct = $this->storefrontProduct($category, 'iPhone 13 128 GB', 'KT-IP13');

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
    protected function submit(?Store $store = null, ?string $key = null, ?int $shopId = null): StorefrontOrder
    {
        $store ??= $this->store;
        $quantities = Cart::query()->where('user_id', $this->buyer->id)->sole()->items()->get()
            ->mapWithKeys(fn ($item): array => [(int) $item->product_id => (int) $item->quantity]);
        $total = collect(app(QuoteBranchOrder::class)->branches($quantities))->firstWhere('store_id', $store->id)['total_minor'] ?? 0;

        return app(SubmitBranchOrder::class)($this->buyer, $shopId ?? $this->shop->id, $store->id, $total, $key ?? (string) Str::uuid(), null);
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

    private function storefrontProduct(Category $category, string $name, string $code): Product
    {
        $slug = Str::slug($name);
        $product = Product::factory()->create(['name' => $name, 'code' => $code, 'slug' => $slug, 'tr_slug' => $slug.'-tr', 'ar_slug' => $slug.'-ar', 'tr_name' => $name.' TR', 'ar_name' => $name.' AR', 'is_active' => true, 'category_id' => $category->id]);
        $currencyId = (int) (DB::table('currencies')->value('id') ?? DB::table('currencies')->insertGetId(['code' => 'TRY', 'name' => 'Turkish lira', 'symbol' => 'TL', 'is_default' => true, 'created_at' => now(), 'updated_at' => now()]));
        ProductPrice::factory()->create(['product_id' => $product->id, 'currency_id' => $currencyId, 'base_price' => 20.00, 'converted_price' => 20.00]);

        return $product;
    }
}
