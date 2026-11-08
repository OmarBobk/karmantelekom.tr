<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Inventory\RemoveUnit;
use App\Actions\Network\PublishUnitListing;
use App\Actions\Network\UnpublishUnitListing;
use App\Enums\AdjustmentReason;
use App\Enums\TrackingMode;
use App\Enums\UnitCondition;
use App\Livewire\Backend\Inventory\UnitDetail;
use App\Livewire\Pos\NetworkListings;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\NetworkListing;
use App\Models\ProductDefinition;
use App\Models\Store;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use ReflectionObject;
use ReflectionProperty;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\InteractsWithPos;
use Tests\TestCase;

class StoreNetworkListingTest extends TestCase
{
    use InteractsWithPos;
    use RefreshDatabase;

    private Store $otherStore;

    private User $otherCashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCounter();
        $this->useEnglishLocale();
        $this->otherStore = Store::factory()->create(['code' => 'KT02', 'name' => 'Karman Kadikoy']);
        $this->otherCashier = $this->createCashier($this->otherStore, name: 'Kadikoy cashier');
        $this->phone->update(['minimum_selling_price' => '11000.00']);
    }

    #[Test]
    public function publishing_records_who_where_and_when_once_without_moving_stock(): void
    {
        $movements = InventoryMovement::count();

        $this->travelTo(now()->startOfMinute());
        $listing = $this->publish();
        $this->assertSame($this->store->id, $listing->store_id);
        $this->assertSame($this->owner->id, $listing->published_by);
        $this->assertTrue($listing->published_at->equalTo(now()));

        $this->travel(5)->minutes();
        $again = $this->publish();
        $this->assertSame($listing->id, $again->id);
        $this->assertTrue($again->published_at->equalTo($listing->published_at));
        $this->assertSame(1, NetworkListing::count());
        $this->assertSame(1, $this->auditEvents('listing_published'));

        app(UnpublishUnitListing::class)($this->owner, $this->store, $this->phone->id);
        app(UnpublishUnitListing::class)($this->owner, $this->store, $this->phone->id);
        $this->assertSame(1, $this->auditEvents('listing_unpublished'));
        $this->assertFalse($listing->fresh()->isPublished());

        $this->publish();
        $this->assertSame(1, NetworkListing::count());
        $this->assertTrue($listing->fresh()->isPublished());
        $this->assertNull($listing->fresh()->unpublished_by);
        $this->assertSame(2, $this->auditEvents('listing_published'));
        $this->assertSame($movements, InventoryMovement::count());
    }

    #[Test]
    public function only_an_owner_with_sharing_permission_publishes_an_eligible_device_of_their_branch(): void
    {
        $this->assertRefused(fn () => app(PublishUnitListing::class)($this->createCashier(), $this->store, $this->phone->id));

        $this->expectModelNotFound(fn () => app(PublishUnitListing::class)($this->owner, $this->otherStore, $this->phone->id));

        $unpriced = $this->registerDevice($this->owner, $this->store, $this->usedPhone, self::imei('35693803564381'));
        $this->assertListingError(fn () => app(PublishUnitListing::class)($this->owner, $this->store, $unpriced->id), 'Set a selling price');

        $this->usedPhone->update(['is_active' => false]);
        $this->assertListingError(fn () => $this->publish(), 'inactive');
        $this->usedPhone->update(['is_active' => true]);

        $this->revokeFromOwners('share_listings');
        $this->assertRefused(fn () => $this->publish());
        $this->assertSame(0, NetworkListing::count());
    }

    #[Test]
    public function the_owner_shares_and_withdraws_a_device_from_its_page(): void
    {
        Livewire::actingAs($this->owner)->test(UnitDetail::class, ['store' => $this->store, 'unit' => $this->phone])
            ->assertSee('Not shared')
            ->call('publishListing')
            ->assertHasNoErrors()
            ->assertSee('Shared with other branches')
            ->call('unpublishListing')
            ->assertSee('Not shared');

        $this->phone->update(['selling_price' => null, 'minimum_selling_price' => null]);

        Livewire::actingAs($this->owner)->test(UnitDetail::class, ['store' => $this->store, 'unit' => $this->phone])
            ->assertSee('Set a selling price before sharing this device.')
            ->assertDontSee('Share with other branches')
            ->call('publishListing')
            ->assertHasErrors('listing');
    }

    #[Test]
    public function employees_with_counter_access_discover_other_branches_listings_without_device_secrets(): void
    {
        $this->publish();
        $ownListing = $this->registerDevice($this->owner, $this->otherStore, $this->usedPhone, self::imei('35693803564382'));
        $ownListing->update(['selling_price' => '9000.00']);
        app(PublishUnitListing::class)($this->owner, $this->otherStore, $ownListing->id);

        $response = $this->actingAs($this->otherCashier)->get(route('pos.network', $this->otherStore))
            ->assertOk()
            ->assertSee(['iPhone 13', '128 GB', UnitCondition::B->label(), '12.500,00', 'Karman Fatih', 'KT01'])
            ->assertDontSee('9.000,00');

        foreach ([$this->phoneImei, $this->phoneSecondImei, '11.000,00', '11000', 'selling_price', 'inventory_unit_id', '"unit_id"'] as $secret) {
            $response->assertDontSee($secret, false);
        }

        $this->actingAs($this->otherCashier)->get(route('pos.network', $this->store))->assertForbidden();
        $this->actingAs($this->createCashier($this->otherStore, withPos: false, name: 'No counter'))->get(route('pos.network', $this->otherStore))->assertForbidden();
    }

    #[Test]
    public function discovery_searches_by_product_or_model_and_filters_by_condition_and_branch(): void
    {
        $thirdStore = Store::factory()->create(['code' => 'KT03', 'name' => 'Karman Uskudar']);
        $galaxy = ProductDefinition::factory()->priced('15000.00')->create(['name' => 'Galaxy S24', 'model' => 'SM-S921', 'tracking_mode' => TrackingMode::SERIALIZED]);
        $sealed = $this->registerDevice($this->owner, $thirdStore, $galaxy, null, 'R5CX1234', condition: UnitCondition::NEW);
        $this->publish();
        app(PublishUnitListing::class)($this->owner, $thirdStore, $sealed->id);

        $page = Livewire::actingAs($this->otherCashier)->test(NetworkListings::class, ['store' => $this->otherStore]);
        $this->assertEqualsCanonicalizing(['iPhone 13', 'Galaxy S24'], $this->listedProducts($page));

        $page->set('search', 'SM-S9')->call('applyFilters');
        $this->assertSame(['Galaxy S24'], $this->listedProducts($page));
        $page->set('search', 'iphone')->call('applyFilters');
        $this->assertSame(['iPhone 13'], $this->listedProducts($page));

        $page->set('search', '')->call('applyFilters')->set('condition', UnitCondition::B->value);
        $this->assertSame(['iPhone 13'], $this->listedProducts($page));

        $page->set('condition', '')->set('owningStore', (string) $thirdStore->id);
        $this->assertSame(['Galaxy S24'], $this->listedProducts($page));

        $page->set('owningStore', (string) $this->otherStore->id);
        $this->assertEqualsCanonicalizing(['iPhone 13', 'Galaxy S24'], $this->listedProducts($page));

        $publicState = collect((new ReflectionObject($page->instance()))->getProperties(ReflectionProperty::IS_PUBLIC))
            ->reject(fn (ReflectionProperty $property): bool => $property->isStatic())
            ->map(fn (ReflectionProperty $property): string => $property->getName())
            ->all();
        $this->assertEqualsCanonicalizing(['storeId', 'search', 'condition', 'owningStore', 'paginators'], $publicState);
    }

    #[Test]
    public function a_device_sold_at_the_counter_disappears_from_discovery(): void
    {
        $this->publish();
        $this->assertDiscovered(['iPhone 13']);

        $attempt = $this->review($this->owner, $this->phoneLine());
        $this->approveCard($this->owner, $attempt, $this->createTerminal(), '12500');
        $this->finalize($this->owner, $attempt);

        $this->assertDiscovered([]);
        $this->assertTrue(NetworkListing::query()->sole()->isPublished());
    }

    #[Test]
    public function devices_in_review_removed_or_held_by_another_branch_disappear_from_discovery(): void
    {
        $this->publish();

        app(RemoveUnit::class)($this->owner, $this->store, $this->phone->id, AdjustmentReason::LOST, 'Missing', (string) Str::uuid(), 0);
        $this->assertDiscovered([]);

        $this->phone->update(['status' => 'review', 'removed_at' => null]);
        $this->assertDiscovered([]);

        $this->phone->update(['status' => 'available']);
        $this->assertDiscovered(['iPhone 13']);

        InventoryUnit::query()->whereKey($this->phone->id)->update(['store_id' => $this->otherStore->id]);
        $this->assertDiscovered([]);
    }

    private function publish(): NetworkListing
    {
        return app(PublishUnitListing::class)($this->owner, $this->store, $this->phone->id);
    }

    /**
     * @param  list<string>  $products
     */
    private function assertDiscovered(array $products): void
    {
        $page = Livewire::actingAs($this->otherCashier)->test(NetworkListings::class, ['store' => $this->otherStore]);

        $this->assertSame($products, $this->listedProducts($page));
    }

    /**
     * @return list<string>
     */
    private function listedProducts(mixed $page): array
    {
        return collect($page->viewData('listings')->items())->pluck('product')->all();
    }

    private function auditEvents(string $event): int
    {
        return Activity::query()->where('log_name', 'store_network')->where('event', $event)->count();
    }

    private function assertListingError(callable $action, string $fragment): void
    {
        try {
            $action();
            $this->fail('Expected a listing validation error.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString($fragment, $exception->errors()['listing'][0] ?? '');
        }
    }

    private function assertRefused(callable $action): void
    {
        try {
            $action();
            $this->fail('Expected the action to be refused.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }
    }

    private function expectModelNotFound(callable $action): void
    {
        try {
            $action();
            $this->fail('Expected the device to be outside this branch.');
        } catch (ModelNotFoundException) {
            $this->addToAssertionCount(1);
        }
    }
}
