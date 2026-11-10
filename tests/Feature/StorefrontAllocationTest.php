<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Network\ApproveReservation;
use App\Actions\Network\DispatchReservation;
use App\Actions\Network\PublishUnitListing;
use App\Actions\Network\RequestReservation;
use App\Actions\Network\SetReservationHold;
use App\Actions\Network\SharedListings;
use App\Actions\Pos\ResolveTerminalPayment;
use App\Actions\Pos\ReviewCheckout;
use App\Actions\Storefront\SetStorefrontDevice;
use App\Actions\Storefront\SetStorefrontQuantity;
use App\Actions\Storefront\StorefrontAvailability;
use App\Enums\UnitStatus;
use App\Livewire\Backend\Inventory\ProductInventory;
use App\Livewire\Backend\Inventory\UnitDetail;
use App\Models\InventoryMovement;
use App\Models\NetworkListing;
use App\Models\Store;
use App\Models\StorefrontAllocation;
use App\Models\User;
use Closure;
use Database\Seeders\OperationalStoreSetupSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\InteractsWithPos;
use Tests\TestCase;

class StorefrontAllocationTest extends TestCase
{
    use InteractsWithPos;
    use RefreshDatabase;

    private Store $otherStore;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCounter();
        $this->useEnglishLocale();
        $this->travelTo(now()->startOfMinute());
        $this->otherStore = Store::factory()->create(['code' => 'KT02', 'name' => 'Karman Kadikoy']);
        $this->cashier = $this->createCashier(name: 'Fatih cashier');
    }

    #[Test]
    public function only_owners_with_the_allocation_permission_can_offer_branch_stock_online(): void
    {
        $this->assertDenied(fn () => $this->setQuantity(3, $this->cashier));
        $this->assertDenied(fn () => $this->setDevice(true, $this->cashier));

        $publisher = User::factory()->create()->assignRole('salesperson');
        $publisher->givePermissionTo(OperationalStoreSetupSeeder::PUBLISH_STOREFRONT_PERMISSION);
        $this->assertDenied(fn () => $this->setQuantity(3, $publisher));

        $this->revokeFromOwners(OperationalStoreSetupSeeder::ALLOCATE_STOREFRONT_STOCK_PERMISSION);
        $this->assertDenied(fn () => $this->setQuantity(3, $this->owner->fresh()));
        $this->assertSame(0, StorefrontAllocation::count());

        Livewire::actingAs($this->owner->fresh())
            ->test(ProductInventory::class, ['store' => $this->store, 'productDefinition' => $this->cable])
            ->assertDontSee('data-testid="storefront-allocation"', false)
            ->set('storefrontQuantity', '3')
            ->call('saveStorefrontQuantity')
            ->assertForbidden();
    }

    #[Test]
    public function a_device_can_only_be_offered_by_the_branch_that_holds_it(): void
    {
        $this->assertDenied(fn () => app(SetStorefrontDevice::class)($this->owner, $this->otherStore, $this->phone->id, true), ModelNotFoundException::class);
        $this->assertSame(0, StorefrontAllocation::count());
    }

    #[Test]
    public function a_quantity_allocation_is_validated_against_stock_and_never_moves_it(): void
    {
        $movements = InventoryMovement::count();

        $this->assertValidationError(fn () => $this->setQuantity(11), 'cannot be more than the 10 on hand');
        $this->assertValidationError(fn () => $this->setQuantity(-1), 'whole number from 0');
        $this->assertValidationError(fn () => app(SetStorefrontQuantity::class)($this->owner, $this->store, $this->usedPhone->id, 1), 'only for quantity-tracked');
        $this->assertValidationError(fn () => app(SetStorefrontQuantity::class)($this->owner, $this->otherStore, $this->cable->id, 1), 'cannot be more than the 0 on hand');

        $allocation = $this->setQuantity(4);
        $this->assertSame(4, $allocation->quantity);
        $this->assertSame($this->owner->id, $allocation->updated_by);
        $this->setQuantity(4);
        $this->assertSame(1, $this->auditEvents('allocation_set'));

        $this->setDevice(true);
        $this->setDevice(true);
        $this->assertSame(1, $this->auditEvents('device_allocated'));

        $this->assertSame(10, $this->cableStock());
        $this->assertSame(UnitStatus::AVAILABLE, $this->phone->fresh()->status);
        $this->assertSame($movements, InventoryMovement::count());

        $this->setQuantity(0);
        $this->setDevice(false);
        $this->assertSame([], $this->available([$this->cable->id, $this->usedPhone->id]));
        $this->assertSame($movements, InventoryMovement::count());
    }

    #[Test]
    public function an_unpriced_or_unavailable_device_cannot_be_offered(): void
    {
        $this->phone->update(['selling_price' => null]);
        $this->assertValidationError(fn () => $this->setDevice(true), 'Set a selling price');

        $this->phone->update(['selling_price' => '12500.00', 'status' => UnitStatus::IN_REPAIR]);
        $this->assertValidationError(fn () => $this->setDevice(true), 'so it cannot be offered online');
        $this->assertSame(0, StorefrontAllocation::count());
    }

    #[Test]
    public function online_eligibility_is_independent_of_store_network_sharing(): void
    {
        $this->setDevice(true);
        $this->assertSame(0, NetworkListing::count());
        $this->assertSame(0, app(SharedListings::class)($this->otherStore)->total());

        $listing = app(PublishUnitListing::class)($this->owner, $this->store, $this->phone->id, NetworkListing::UNLISTED_REVISION);
        $this->setDevice(false);
        $this->assertTrue($listing->fresh()->isPublished());
        $this->assertSame(1, app(SharedListings::class)($this->otherStore)->total());
        $this->assertSame([], $this->available([$this->usedPhone->id]));
    }

    #[Test]
    public function quantity_availability_follows_the_smaller_of_the_allocation_and_current_stock(): void
    {
        $this->setQuantity(4);
        $this->assertSame([$this->cable->id => 4], $this->available([$this->cable->id]));

        $this->finalize($this->cashier, $this->review($this->cashier, $this->cableLine(7)), '349.30');
        $this->assertSame(3, $this->cableStock());
        $this->assertSame([$this->cable->id => 3], $this->available([$this->cable->id]));
        $this->assertSame(4, StorefrontAllocation::query()->sole()->quantity);

        $this->cable->update(['is_active' => false]);
        $this->assertSame([], $this->available([$this->cable->id]));
    }

    #[Test]
    public function device_availability_follows_sales_status_changes_reservations_payment_claims_and_transfers(): void
    {
        $this->setDevice(true);
        $this->assertSame([$this->usedPhone->id => 1], $this->available([$this->usedPhone->id]));

        $this->phone->update(['status' => UnitStatus::IN_REPAIR]);
        $this->assertSame([], $this->available([$this->usedPhone->id]));
        $this->phone->update(['status' => UnitStatus::AVAILABLE]);

        $requester = $this->createCashier($this->otherStore, name: 'Kadikoy cashier');
        $listing = app(PublishUnitListing::class)($this->owner, $this->store, $this->phone->id, NetworkListing::UNLISTED_REVISION);
        app(SetReservationHold::class)($this->owner, $this->store, 60);
        $reservation = app(RequestReservation::class)($requester, $this->otherStore, $listing->id, 1);
        $this->assertSame([$this->usedPhone->id => 1], $this->available([$this->usedPhone->id]));

        app(ApproveReservation::class)($this->owner, $this->store, $reservation->id);
        $this->assertSame([], $this->available([$this->usedPhone->id]));

        $terminal = $this->createTerminal();
        $linked = app(ReviewCheckout::class)($this->cashier, $this->store, $this->phoneLine(), reservationId: $reservation->id);
        $paymentKey = $this->startTerminalPayment($this->cashier, $linked, $terminal, '12500.00')->terminal_payment_key;
        $this->travel(61)->minutes();
        $this->assertFalse($reservation->fresh()->isActive());
        $this->assertSame([], $this->available([$this->usedPhone->id]), 'An unresolved payment claim keeps the device unavailable after expiry.');

        app(ResolveTerminalPayment::class)($this->owner, $this->store, $linked->key, $paymentKey, 'no_charge', null, 'Terminal declined');
        $this->assertSame([$this->usedPhone->id => 1], $this->available([$this->usedPhone->id]));

        $transfer = app(RequestReservation::class)($requester, $this->otherStore, $listing->id, $listing->fresh()->revision);
        app(ApproveReservation::class)($this->owner, $this->store, $transfer->id);
        app(DispatchReservation::class)($this->owner, $this->store, $transfer->id);
        $this->assertSame(UnitStatus::IN_TRANSIT, $this->phone->fresh()->status);
        $this->assertSame([], $this->available([$this->usedPhone->id]));
    }

    #[Test]
    public function a_sold_device_is_no_longer_available(): void
    {
        $this->setDevice(true);
        $this->finalize($this->cashier, $this->review($this->cashier, $this->phoneLine()), '12500.00');

        $this->assertSame(UnitStatus::SOLD, $this->phone->fresh()->status);
        $this->assertSame([], $this->available([$this->usedPhone->id]));
    }

    #[Test]
    public function owners_allocate_from_the_inventory_and_device_pages(): void
    {
        Livewire::actingAs($this->owner)
            ->test(ProductInventory::class, ['store' => $this->store, 'productDefinition' => $this->cable])
            ->assertSee('data-testid="storefront-allocation"', false)
            ->set('storefrontQuantity', '12')
            ->call('saveStorefrontQuantity')
            ->assertHasErrors('allocation')
            ->assertSee('cannot be more than the 10 on hand')
            ->set('storefrontQuantity', '6')
            ->call('saveStorefrontQuantity')
            ->assertHasNoErrors();
        $this->assertSame(6, StorefrontAllocation::query()->sole()->quantity);

        Livewire::actingAs($this->owner)
            ->test(UnitDetail::class, ['store' => $this->store, 'unit' => $this->phone])
            ->call('allowOnStorefront')
            ->assertHasNoErrors()
            ->assertSee('Stop offering online');
        $this->assertSame(1, StorefrontAllocation::query()->where('inventory_unit_id', $this->phone->id)->value('quantity'));
    }

    private function setQuantity(int $quantity, ?User $actor = null): ?StorefrontAllocation
    {
        return app(SetStorefrontQuantity::class)($actor ?? $this->owner, $this->store, $this->cable->id, $quantity);
    }

    private function setDevice(bool $eligible, ?User $actor = null): ?StorefrontAllocation
    {
        return app(SetStorefrontDevice::class)($actor ?? $this->owner, $this->store, $this->phone->id, $eligible);
    }

    /**
     * @param  list<int>  $definitionIds
     * @return array<int, int>
     */
    private function available(array $definitionIds): array
    {
        return app(StorefrontAvailability::class)->forDefinitions($definitionIds);
    }

    private function auditEvents(string $event): int
    {
        return Activity::query()->where('log_name', 'storefront')->where('event', $event)->count();
    }

    private function assertValidationError(Closure $action, string $message): void
    {
        try {
            $action();
        } catch (ValidationException $exception) {
            $errors = collect($exception->errors())->flatten();
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
