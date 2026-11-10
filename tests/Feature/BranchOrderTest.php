<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Inventory\RecordStockCount;
use App\Actions\Network\PublishUnitListing;
use App\Actions\Network\RequestReservation;
use App\Actions\Pos\ResolveTerminalPayment;
use App\Actions\Pos\ReviewCheckout;
use App\Actions\Storefront\AcceptBranchOrderPrice;
use App\Actions\Storefront\BranchOrderHolds;
use App\Actions\Storefront\CancelBranchOrder;
use App\Actions\Storefront\ExpireBranchOrders;
use App\Actions\Storefront\RequestBranchOrderPriceChange;
use App\Actions\Storefront\SetStorefrontChannel;
use App\Actions\Storefront\SetStorefrontDevice;
use App\Actions\Storefront\StorefrontAvailability;
use App\Actions\Storefront\StorefrontOrderGuard;
use App\Actions\Storefront\UnlinkStorefrontProduct;
use App\Enums\OrderStatus;
use App\Enums\StorefrontChannel;
use App\Enums\StorefrontOrderStatus;
use App\Enums\UnitStatus;
use App\Livewire\Backend\Orders\OrdersManager;
use App\Livewire\Frontend\BranchOrdersComponent;
use App\Livewire\Frontend\CheckoutComponent;
use App\Livewire\Pos\PosCounter;
use App\Livewire\Pos\StorefrontOrders;
use App\Models\Cart;
use App\Models\CheckoutAttempt;
use App\Models\Customer;
use App\Models\InventoryUnit;
use App\Models\NetworkListing;
use App\Models\Order;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\Store;
use App\Models\StorefrontListing;
use App\Models\StorefrontOrder;
use App\Models\User;
use App\Services\CartService;
use App\Services\Purchasing\LocalizedMoney;
use App\Services\Purchasing\Money;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\Support\InteractsWithBranchOrders;
use Tests\TestCase;

class BranchOrderTest extends TestCase
{
    use InteractsWithBranchOrders;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpBranchOrders();
    }

    #[Test]
    public function with_the_flag_off_linked_stock_stays_display_only_and_the_order_pages_are_hidden(): void
    {
        config(['storefront.branch_orders' => false]);
        $cart = $this->fillCart([$this->cableProduct->id => 1]);

        $this->assertError(fn () => $this->submit(), 'can only be bought in our stores', 'cart');

        Livewire::actingAs($this->buyer)->test(CheckoutComponent::class)
            ->set('selectedShopId', $this->shop->id)
            ->call('placeOrder')
            ->assertHasErrors('cart')
            ->assertDontSee('Pickup branch');
        $this->assertSame(0, Order::count());
        $this->assertSame(0, $cart->items()->count());

        $this->withoutExceptionHandling();
        $this->assertThrows(fn () => Livewire::actingAs($this->buyer)->test(BranchOrdersComponent::class), NotFoundHttpException::class);
        $this->assertThrows(fn () => Livewire::actingAs($this->owner)->test(StorefrontOrders::class, ['store' => $this->store]), NotFoundHttpException::class);
    }

    #[Test]
    public function checkout_submits_a_pending_order_that_holds_nothing_and_a_retry_returns_it(): void
    {
        $this->fillCart([$this->cableProduct->id => 2, $this->phoneProduct->id => 1]);

        $checkout = Livewire::actingAs($this->buyer)->test(CheckoutComponent::class)
            ->set('selectedShopId', $this->shop->id)
            ->assertSee('Pickup branch')
            ->assertSee('Karman Fatih')
            ->assertSet('selectedStoreId', $this->store->id);
        $key = $checkout->get('submissionKey');
        $checkout->call('placeOrder')->assertHasNoErrors()->assertRedirect(route('storefront.orders'));

        $order = StorefrontOrder::query()->sole();
        $this->assertSame(StorefrontOrderStatus::PENDING, $order->status);
        $this->assertSame(1259980, $order->totalMinor());
        $this->assertSame(OrderStatus::PENDING, $order->order->status);
        $this->assertSame([2, 1], $order->order->items()->orderBy('id')->pluck('quantity')->all());
        $this->assertSame(0, (int) $order->lines()->sum('held_quantity'));
        $this->assertSame(0, $order->lines()->whereNotNull('active_unit_id')->count());
        $this->assertSame([$this->cableProduct->id => 4, $this->phoneProduct->id => 1], app(StorefrontAvailability::class)->forProducts([$this->cableProduct->id, $this->phoneProduct->id]));
        $this->assertSame(0, Cart::query()->sole()->items()->count());

        $this->assertSame($order->id, $this->submit(key: $key)->id);
        $this->assertSame(1, StorefrontOrder::count());
        $this->assertSame(1, Order::count());

        $this->review($this->owner, $this->cableLine(10) + $this->phoneLine());

        Livewire::actingAs($this->buyer)->test(BranchOrdersComponent::class)
            ->assertSee('Awaiting confirmation')
            ->assertSee('Karman Fatih');
    }

    #[Test]
    public function submission_requires_shop_access_and_publication_to_the_buyers_channel(): void
    {
        $this->fillCart([$this->cableProduct->id => 1]);
        $otherShop = Shop::factory()->create();

        $this->assertError(fn () => $this->submit(shopId: $otherShop->id), 'You cannot order for this shop', 'selectedShopId');

        $listing = StorefrontListing::query()->where('product_id', $this->cableProduct->id)->sole();
        app(SetStorefrontChannel::class)($this->owner, $listing->id, StorefrontChannel::RETAIL, false);
        $this->assertError(fn () => $this->submit(), 'no longer offered online', 'cart');

        $this->assertSame(0, StorefrontOrder::count());
        $this->assertSame(1, Cart::query()->sole()->items()->count());
    }

    #[Test]
    public function a_cart_mixing_branch_and_legacy_products_is_refused_and_kept(): void
    {
        $legacy = Product::factory()->create(['is_active' => true, 'category_id' => $this->cableProduct->category_id]);
        $this->fillCart([$this->cableProduct->id => 1, $legacy->id => 2]);

        Livewire::actingAs($this->buyer)->test(CheckoutComponent::class)
            ->set('selectedShopId', $this->shop->id)
            ->assertSee('ordered from a branch and others are not')
            ->call('placeOrder')
            ->assertHasErrors('cart');
        $this->assertError(fn () => $this->submit(), 'cannot be ordered together', 'cart');

        $this->assertSame(0, Order::count());
        $this->assertEqualsCanonicalizing([$this->cableProduct->id, $legacy->id], Cart::query()->sole()->items()->pluck('product_id')->all());
    }

    #[Test]
    public function only_the_chosen_branch_with_permission_confirms_and_a_retry_changes_nothing(): void
    {
        $order = $this->pendingOrder();
        $otherStore = Store::factory()->create(['code' => 'KT02']);
        $cashier = $this->createCashier();

        $this->assertThrows(fn () => $this->confirm($order, store: $otherStore), ModelNotFoundException::class);
        $this->assertThrows(fn () => $this->confirm($order, actor: $cashier), AuthorizationException::class);
        $this->assertError(fn () => $this->confirm($order, hours: 0), 'pickup window from 1', 'pickup_hours');
        $this->assertError(fn () => $this->confirm($order, hours: StorefrontOrder::MAX_PICKUP_HOURS + 1), 'from 1 to 336 hours', 'pickup_hours');

        $this->travelTo(now()->startOfMinute());
        $confirmed = $this->confirm($order);
        $this->assertSame(StorefrontOrderStatus::CONFIRMED, $confirmed->status);
        $this->assertTrue($confirmed->expires_at->equalTo(now()->addHours(48)));
        $this->assertSame(OrderStatus::CONFIRMED, $order->order->fresh()->status);
        $this->assertSame([$this->cable->id => 2], BranchOrderHolds::heldQuantities($this->store->id, [$this->cable->id]));
        $this->assertSame($order->id, BranchOrderHolds::unitHolds([$this->phone->id])->first()->storefront_order_id);

        $this->travel(5)->minutes();
        $this->assertTrue($this->confirm($order)->expires_at->equalTo($confirmed->expires_at));
        $this->assertError(fn () => $this->confirm($order, hours: 24), 'not awaiting confirmation', 'order');
        $this->assertSame([$this->cable->id => 2], BranchOrderHolds::heldQuantities($this->store->id, [$this->cable->id]));
    }

    #[Test]
    public function a_device_at_another_price_needs_the_buyers_acceptance_before_confirmation(): void
    {
        $order = $this->pendingOrder(cables: 1);
        $dearer = $this->registerDevice($this->owner, $this->store, $this->usedPhone, self::imei('35693803564414'));
        $dearer->update(['selling_price' => '13000.00']);
        app(SetStorefrontDevice::class)($this->owner, $this->store, $dearer->id, true);
        $phoneLine = $order->lines()->get()->first->tracksUnits();
        $choice = [$phoneLine->id => $dearer->id];

        $this->assertError(fn () => $this->confirm($order, devices: $choice), 'Request new prices instead', 'lines.'.$phoneLine->id);
        $this->assertError(fn () => app(RequestBranchOrderPriceChange::class)($this->owner, $this->store, $order->id, [$phoneLine->id => $this->phone->id]), 'cost the same', 'order');

        app(RequestBranchOrderPriceChange::class)($this->owner, $this->store, $order->id, $choice);
        $this->assertTrue($order->fresh()->awaitsPriceAcceptance());
        $this->assertError(fn () => $this->confirm($order, devices: $choice), 'not accepted the new prices', 'order');

        app(AcceptBranchOrderPrice::class)($this->buyer, $order->id, $order->fresh()->price_proposal_fingerprint);
        $order->refresh();
        $this->assertSame(1300000 + 4990, $order->totalMinor());
        $this->assertSame('13000.00', $order->order->items()->where('product_id', $this->phoneProduct->id)->value('price'));

        $this->confirm($order, devices: []);
        $this->assertSame($dearer->id, $phoneLine->fresh()->active_unit_id);
    }

    #[Test]
    public function acceptance_binds_to_the_displayed_proposal_and_a_retry_never_accepts_a_newer_one(): void
    {
        $order = $this->pendingOrder(cables: 1);
        $phoneLine = $order->lines()->get()->first->tracksUnits();
        $first = $this->offeredPhone('35693803564414', '13000.00');
        $second = $this->offeredPhone('35693803564422', '14000.00');
        $request = app(RequestBranchOrderPriceChange::class);

        $request($this->owner, $this->store, $order->id, [$phoneLine->id => $first->id]);
        $stale = $order->fresh()->price_proposal_fingerprint;
        $request($this->owner, $this->store, $order->id, [$phoneLine->id => $second->id]);
        $current = $order->fresh()->price_proposal_fingerprint;
        $this->assertNotSame($stale, $current);

        Livewire::actingAs($this->buyer)->test(BranchOrdersComponent::class)
            ->call('acceptPrices', $order->id, $stale)
            ->assertHasErrors('order')
            ->assertSee('changed its proposal')
            ->assertSee(LocalizedMoney::format(1400000))
            ->assertSee('Device offered');
        $this->assertSame(1250000 + 4990, $order->fresh()->totalMinor());
        $this->assertTrue($order->fresh()->awaitsPriceAcceptance());

        Livewire::actingAs($this->buyer)->test(BranchOrdersComponent::class)
            ->call('acceptPrices', $order->id, $current)
            ->assertHasNoErrors();
        $this->assertSame(1400000 + 4990, $order->fresh()->totalMinor());
        $this->assertSame(1400000 + 4990, app(AcceptBranchOrderPrice::class)($this->buyer, $order->id, $current)->totalMinor());
        $this->assertError(fn () => $this->confirm($order, devices: [$phoneLine->id => $first->id]), 'accepted specific devices', 'devices');

        $request($this->owner, $this->store, $order->id, [$phoneLine->id => $first->id]);
        $newer = $order->fresh()->price_proposal_fingerprint;
        app(AcceptBranchOrderPrice::class)($this->buyer, $order->id, $current);

        $order->refresh();
        $this->assertSame($newer, $order->price_proposal_fingerprint);
        $this->assertTrue($order->awaitsPriceAcceptance());
        $this->assertSame(1400000 + 4990, $order->totalMinor());
        $this->assertSame('14000.00', $order->order->items()->where('product_id', $this->phoneProduct->id)->value('price'));
    }

    #[Test]
    public function pickup_of_a_branch_order_refuses_credit_at_review_payment_start_and_finalization(): void
    {
        $order = $this->confirm($this->pendingOrder());

        $this->assertError(fn () => app(ReviewCheckout::class)($this->owner, $this->store, $this->pickupCart($order), null, null, null, '100.00', null, $order->id), 'paid in full at pickup', 'credit_amount');

        $attempt = $this->reviewPickup($order);
        $customer = Customer::factory()->atCounter($this->store)->create();
        CheckoutAttempt::query()->whereKey($attempt->id)->update(['customer_id' => $customer->id, 'credit_amount' => '100.00', 'credit_authorized_by' => $this->owner->id]);
        $attempt->refresh();
        $terminal = $this->createTerminal();

        $this->assertError(fn () => $this->startTerminalPayment($this->owner, $attempt, $terminal, $attempt->total), 'paid in full at pickup', 'storefront_order');
        $this->assertError(fn () => $this->finalize($this->owner, $attempt, '12599.80'), 'paid in full at pickup', 'storefront_order');
        $this->assertSame(0, Sale::count());
        $this->assertSame(StorefrontOrderStatus::CONFIRMED, $order->fresh()->status);
        $this->assertSame(0, $order->lines()->whereNotNull('claimed_attempt_id')->count());
    }

    #[Test]
    public function confirmation_rechecks_the_storefront_product_and_creates_no_holds_when_it_is_withdrawn(): void
    {
        $order = $this->pendingOrder(withPhone: false);
        $lineKey = 'lines.'.$order->lines()->value('id');
        $listing = StorefrontListing::query()->where('product_id', $this->cableProduct->id)->sole();

        $this->cableProduct->update(['is_active' => false]);
        $this->assertError(fn () => $this->confirm($order), 'no longer published', $lineKey);
        $this->cableProduct->update(['is_active' => true]);

        $this->wholesaleTier($this->cableProduct, 40.00, 1, 10);
        app(SetStorefrontChannel::class)($this->owner, $listing->id, StorefrontChannel::WHOLESALE, true);
        app(SetStorefrontChannel::class)($this->owner, $listing->id, StorefrontChannel::RETAIL, false);
        $this->assertError(fn () => $this->confirm($order), 'no longer published', $lineKey);

        app(SetStorefrontChannel::class)($this->owner, $listing->id, StorefrontChannel::WHOLESALE, false);
        app(UnlinkStorefrontProduct::class)($this->owner, $listing->id);
        $this->assertError(fn () => $this->confirm($order), 'no longer published', $lineKey);

        $this->assertSame(StorefrontOrderStatus::PENDING, $order->fresh()->status);
        $this->assertSame([], BranchOrderHolds::heldQuantities($this->store->id, [$this->cable->id]));
        $this->assertSame(0, (int) $order->lines()->sum('held_quantity'));
    }

    #[Test]
    public function retail_orders_use_the_published_retail_price_through_pickup(): void
    {
        $this->retailPrice($this->cableProduct, 45.00);
        $this->fillCart([$this->cableProduct->id => 2]);

        Livewire::actingAs($this->buyer)->test(CheckoutComponent::class)
            ->set('selectedShopId', $this->shop->id)
            ->assertSet('selectedStoreId', $this->store->id)
            ->assertSee(LocalizedMoney::format(4500).' TL');

        $order = $this->submit();
        $this->assertSame(StorefrontChannel::RETAIL, $order->channel);
        $this->assertSame(9000, $order->totalMinor());

        $attempt = $this->reviewPickup($this->confirm($order));
        $this->assertSame(9000, $attempt->totalMinor());
        $sale = $this->finalize($this->owner, $attempt, '90.00');

        $this->assertSame(9000, Money::fromDecimal($sale->total));
        $this->assertSame(StorefrontOrderStatus::FULFILLED, $order->fresh()->status);
    }

    #[Test]
    public function a_wholesale_buyer_pays_the_tier_for_the_quantity_and_a_new_price_needs_a_new_proposal(): void
    {
        $wholesaler = User::factory()->create()->assignRole(Role::findOrCreate('admin'));
        $listing = StorefrontListing::query()->where('product_id', $this->cableProduct->id)->sole();
        $this->wholesaleTier($this->cableProduct, 42.00, 1, 2);
        $this->wholesaleTier($this->cableProduct, 38.00, 3, 10);
        $this->wholesaleTier($this->cableProduct, 30.00, 3, 10, active: false);
        app(SetStorefrontChannel::class)($this->owner, $listing->id, StorefrontChannel::WHOLESALE, true);
        $this->fillCart([$this->cableProduct->id => 3], $wholesaler);

        $order = $this->submit(buyer: $wholesaler);
        $this->assertSame(StorefrontChannel::WHOLESALE, $order->channel);
        $this->assertSame(11400, $order->totalMinor());

        DB::table('wholesale_products')->where('product_id', $this->cableProduct->id)->where('price', 38.00)->update(['price' => 39.00]);
        $this->assertError(fn () => $this->confirm($order), 'Request new prices instead');

        app(RequestBranchOrderPriceChange::class)($this->owner, $this->store, $order->id, []);
        app(AcceptBranchOrderPrice::class)($wholesaler, $order->id, $order->fresh()->price_proposal_fingerprint);
        $this->assertSame(11700, $order->fresh()->totalMinor());

        $attempt = $this->reviewPickup($this->confirm($order));
        $this->assertSame(11700, $attempt->totalMinor());
        $this->finalize($this->owner, $attempt, '117.00');
        $this->assertSame(StorefrontOrderStatus::FULFILLED, $order->fresh()->status);
    }

    #[Test]
    public function an_item_without_a_price_in_the_channel_cannot_be_ordered(): void
    {
        $this->retailPrice($this->cableProduct, 0);
        $this->fillCart([$this->cableProduct->id => 1]);

        Livewire::actingAs($this->buyer)->test(CheckoutComponent::class)
            ->set('selectedShopId', $this->shop->id)
            ->assertSee('no online price')
            ->assertDontSee('Karman Fatih');
        $this->assertError(fn () => $this->submit(), 'no online price', 'cart');
        $this->assertSame(0, StorefrontOrder::count());
        $this->assertSame(1, Cart::query()->sole()->items()->count());

        $this->assertSame([$this->cableProduct->id], StorefrontOrderGuard::displayOnlyProductIds([$this->cableProduct->id, $this->phoneProduct->id], $this->buyer));
        $this->assertError(fn () => app(CartService::class)->addToCart($this->buyer->id, null, $this->cableProduct), 'through checkout', 'cart');
    }

    #[Test]
    public function the_branch_rejects_a_pending_order_with_a_reason_the_buyer_sees(): void
    {
        $order = $this->pendingOrder();

        $this->assertError(fn () => app(CancelBranchOrder::class)->byBranch($this->owner, $this->store, $order->id, 'No stock'), 'rejected with a reason', 'order');

        Livewire::actingAs($this->owner)->test(StorefrontOrders::class, ['store' => $this->store])
            ->call('openCancel', $order->id)
            ->assertSet('rejecting', true)
            ->assertSee('Reason for rejecting')
            ->call('cancel')
            ->assertHasErrors('reason')
            ->set('reason', 'Device sold in store')
            ->call('cancel')
            ->assertHasNoErrors();

        $order->refresh();
        $this->assertSame(StorefrontOrderStatus::REJECTED, $order->status);
        $this->assertSame($this->owner->id, $order->rejected_by);
        $this->assertNotNull($order->rejected_at);
        $this->assertSame('Device sold in store', $order->rejection_reason);
        $this->assertSame(OrderStatus::CANCELED, $order->order->fresh()->status);
        $this->assertSame(StorefrontOrderStatus::REJECTED, app(CancelBranchOrder::class)->rejectByBranch($this->owner, $this->store, $order->id, 'Again')->status);
        $this->assertSame('Device sold in store', $order->fresh()->rejection_reason);
        $this->assertError(fn () => $this->confirm($order), 'not awaiting confirmation', 'order');

        Livewire::actingAs($this->buyer)->test(BranchOrdersComponent::class)
            ->assertSee('Declined by the branch')
            ->assertSee('Device sold in store');
        Livewire::actingAs($this->owner)->test(StorefrontOrders::class, ['store' => $this->store])
            ->set('tab', 'closed')
            ->assertSee('Order #'.$order->order_id);

        $confirmed = $this->confirm($this->pendingOrder(withPhone: false));
        $this->assertError(fn () => app(CancelBranchOrder::class)->rejectByBranch($this->owner, $this->store, $confirmed->id, 'Late'), 'not awaiting confirmation', 'order');
    }

    private function offeredPhone(string $imeiBody, string $price): InventoryUnit
    {
        $unit = $this->registerDevice($this->owner, $this->store, $this->usedPhone, self::imei($imeiBody));
        $unit->update(['selling_price' => $price]);
        app(SetStorefrontDevice::class)($this->owner, $this->store, $unit->id, true);

        return $unit;
    }

    #[Test]
    public function held_stock_is_refused_to_the_counter_store_network_and_storefront(): void
    {
        $this->setStock(3);
        $walkIn = $this->review($this->owner, $this->cableLine(2));
        $order = $this->confirm($this->pendingOrder());
        $terminal = $this->createTerminal();

        $this->assertError(fn () => $this->startTerminalPayment($this->owner, $walkIn, $terminal, $walkIn->total), 'can be sold', 'storefront_order');
        $this->assertNull($walkIn->fresh()->terminal_payment_key);
        $this->assertThrows(fn () => $this->review($this->owner, $this->cableLine(2)), ValidationException::class);
        $this->review($this->owner, $this->cableLine(1));
        $this->assertError(fn () => $this->review($this->owner, $this->phoneLine()), 'held for a confirmed online order');

        $otherStore = Store::factory()->create(['code' => 'KT02']);
        $listing = app(PublishUnitListing::class)($this->owner, $this->store, $this->phone->id, NetworkListing::UNLISTED_REVISION);
        $this->assertThrows(fn () => app(RequestReservation::class)($this->owner, $otherStore, $listing->id, $listing->revision), ValidationException::class);

        $this->assertSame([$this->cableProduct->id => 1, $this->phoneProduct->id => 0], app(StorefrontAvailability::class)->forProducts([$this->cableProduct->id, $this->phoneProduct->id]));
        $this->assertSame(StorefrontOrderStatus::CONFIRMED, $order->status);
    }

    #[Test]
    public function pickup_completes_the_sale_fulfills_the_order_and_consumes_its_holds_once(): void
    {
        $order = $this->confirm($this->pendingOrder());

        $this->assertError(fn () => $this->reviewPickup($order, $this->cableLine(1) + $this->phoneLine()), 'exactly the online order', 'storefront_order');
        $this->assertError(fn () => $this->reviewPickup($order, array_replace($this->pickupCart($order), $this->cableLine(2, ['adjusted_price' => '40.00', 'adjustment_reason' => 'Loyal buyer']))), 'exactly the online order');

        Livewire::actingAs($this->owner)->withQueryParams(['order' => $order->id])->test(PosCounter::class, ['store' => $this->store])
            ->assertSet('storefrontOrderId', $order->id)
            ->assertSee('Collecting online order #'.$order->order_id);

        $attempt = $this->reviewPickup($order);
        $this->assertSame($order->id, $attempt->storefront_order_id);
        $this->assertSame(1259980, $attempt->totalMinor());

        $sale = $this->finalize($this->owner, $attempt, '12599.80');
        $this->assertSame($sale->id, $this->finalize($this->owner, $attempt->fresh(), '12599.80')->id);

        $order->refresh();
        $this->assertSame(StorefrontOrderStatus::FULFILLED, $order->status);
        $this->assertSame($sale->id, $order->sale_id);
        $this->assertSame(OrderStatus::DELIVERED, $order->order->status);
        $this->assertSame([], BranchOrderHolds::heldQuantities($this->store->id, [$this->cable->id]));
        $this->assertTrue(BranchOrderHolds::unitHolds([$this->phone->id])->isEmpty());
        $this->assertSame(8, $this->cableStock());
        $this->assertSame(UnitStatus::SOLD, $this->phone->fresh()->status);
    }

    #[Test]
    public function buyer_and_branch_cancellation_release_holds_but_wait_for_a_started_payment(): void
    {
        $pending = $this->pendingOrder(withPhone: false);
        app(CancelBranchOrder::class)->byBuyer($this->buyer, $pending->id);
        $this->assertSame(StorefrontOrderStatus::CANCELLED, $pending->fresh()->status);
        $this->assertSame(OrderStatus::CANCELED, $pending->order->fresh()->status);
        $this->assertSame(StorefrontOrderStatus::CANCELLED, app(CancelBranchOrder::class)->byBuyer($this->buyer, $pending->id)->status);

        $order = $this->confirm($this->pendingOrder());
        $this->assertError(fn () => app(CancelBranchOrder::class)->byBuyer($this->buyer, $order->id), 'can no longer be cancelled here', 'order');
        $this->assertError(fn () => app(CancelBranchOrder::class)->byBranch($this->owner, $this->store, $order->id, ' '), 'Give a reason', 'reason');

        $terminal = $this->createTerminal();
        $attempt = $this->reviewPickup($order);
        $paymentKey = $this->startTerminalPayment($this->owner, $attempt, $terminal, $attempt->total)->terminal_payment_key;
        $this->assertSame(2, $order->lines()->whereNotNull('claimed_attempt_id')->count());
        $this->assertError(fn () => app(CancelBranchOrder::class)->byBranch($this->owner, $this->store, $order->id, 'Buyer called'), 'Resolve it at the counter', 'order');

        app(ResolveTerminalPayment::class)($this->owner, $this->store, $attempt->key, $paymentKey, 'no_charge', null, 'Terminal declined');
        $this->assertSame(0, $order->lines()->whereNotNull('claimed_attempt_id')->count());

        app(CancelBranchOrder::class)->byBranch($this->owner, $this->store, $order->id, 'Buyer called');
        $order->refresh();
        $this->assertSame(StorefrontOrderStatus::CANCELLED, $order->status);
        $this->assertSame('Buyer called', $order->cancellation_reason);
        $this->assertSame([], BranchOrderHolds::heldQuantities($this->store->id, [$this->cable->id]));
        $this->review($this->owner, $this->phoneLine());
    }

    #[Test]
    public function expiry_releases_unpaid_holds_and_keeps_a_started_payment_protected(): void
    {
        $this->travelTo(now()->startOfMinute());
        $unpaid = $this->confirm($this->pendingOrder(withPhone: false), hours: 1);
        $paid = $this->confirm($this->pendingOrder(cables: 1), hours: 1);
        $terminal = $this->createTerminal();
        $attempt = $this->reviewPickup($paid);
        $this->approveCard($this->owner, $attempt, $terminal, $attempt->total);

        $this->travel(61)->minutes();
        $this->assertSame([$this->cable->id => 1], BranchOrderHolds::heldQuantities($this->store->id, [$this->cable->id]));
        $this->assertError(fn () => $this->reviewPickup($unpaid), 'pickup window for this order has ended', 'storefront_order');

        $this->artisan('storefront:expire-orders')->assertSuccessful();
        $this->assertSame(0, app(ExpireBranchOrders::class)());

        $this->assertSame(StorefrontOrderStatus::EXPIRED, $unpaid->fresh()->status);
        $this->assertSame(OrderStatus::CANCELED, $unpaid->order->fresh()->status);
        $this->assertSame(0, (int) $unpaid->lines()->sum('held_quantity'));
        $this->assertSame(StorefrontOrderStatus::CONFIRMED, $paid->fresh()->status);

        $sale = $this->finalize($this->owner, $attempt->fresh());
        $this->assertSame($sale->id, $paid->fresh()->sale_id);
        $this->assertSame(StorefrontOrderStatus::FULFILLED, $paid->fresh()->status);
    }

    #[Test]
    public function a_count_below_held_stock_is_recorded_and_blocks_pickup_until_resolved(): void
    {
        $order = $this->confirm($this->pendingOrder(cables: 3, withPhone: false));
        $attempt = $this->reviewPickup($order);
        $terminal = $this->createTerminal();

        $this->setStock(2);
        $this->assertSame(2, $this->cableStock());

        $this->assertError(fn () => $this->startTerminalPayment($this->owner, $attempt, $terminal, $attempt->total), 'stock is below what online orders hold', 'storefront_order');
        $this->assertSame(0, $order->lines()->whereNotNull('claimed_attempt_id')->count());

        Livewire::actingAs($this->owner)->test(StorefrontOrders::class, ['store' => $this->store])
            ->assertSee('Stock short: review')
            ->assertDontSee(route('pos.counter', ['store' => $this->store, 'order' => $order->id]));

        $this->setStock(3);
        $this->startTerminalPayment($this->owner, $attempt, $terminal, $attempt->total);
        $this->assertSame(1, $order->lines()->whereNotNull('claimed_attempt_id')->count());
    }

    #[Test]
    public function the_branch_page_confirms_and_cancels_and_the_legacy_editor_cannot_change_a_branch_order(): void
    {
        $order = $this->pendingOrder();

        Livewire::actingAs($this->owner)->test(StorefrontOrders::class, ['store' => $this->store])
            ->assertSee('Order #'.$order->order_id)
            ->call('openConfirm', $order->id)
            ->assertSet('deviceChoices', [$order->lines()->get()->first->tracksUnits()->id => $this->phone->id])
            ->assertSet('pickupHours', '48')
            ->call('confirm')
            ->assertHasNoErrors()
            ->assertSet('confirmingId', null);
        $this->assertSame(StorefrontOrderStatus::CONFIRMED, $order->fresh()->status);

        Livewire::actingAs($this->owner)->test(OrdersManager::class)
            ->call('updateOrderStatus', $order->order_id, OrderStatus::DELIVERED->value);
        $this->assertSame(OrderStatus::CONFIRMED, $order->order->fresh()->status);

        Livewire::actingAs($this->owner)->test(StorefrontOrders::class, ['store' => $this->store])
            ->call('openCancel', $order->id)
            ->call('cancel')
            ->assertHasErrors('reason')
            ->set('reason', 'Device damaged')
            ->call('cancel')
            ->assertHasNoErrors();
        $this->assertSame(StorefrontOrderStatus::CANCELLED, $order->fresh()->status);
        $this->assertTrue(BranchOrderHolds::unitHolds([$this->phone->id])->isEmpty());
    }

    private function setStock(int $counted): void
    {
        app(RecordStockCount::class)($this->owner, $this->store, $this->cable, $counted, RecordStockCount::latestMovementId($this->store, $this->cable), 'Shelf count', (string) Str::uuid());
    }

    private function assertError(Closure $action, string $message, ?string $key = null): void
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
}
