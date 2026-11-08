<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Inventory\RecordOpeningBalance;
use App\Actions\Inventory\RegisterOpeningUnit;
use App\Enums\TrackingMode;
use App\Enums\UnitCondition;
use App\Enums\UnitStatus;
use App\Livewire\Backend\Inventory\InventoryStores;
use App\Livewire\Backend\Inventory\ProductInventory;
use App\Livewire\Backend\Inventory\StoreInventory;
use App\Livewire\Backend\Inventory\UnitDetail;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\ProductDefinition;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\InteractsWithOperationalInventory;
use Tests\TestCase;

class InventoryUiTest extends TestCase
{
    use InteractsWithOperationalInventory;
    use RefreshDatabase;

    private User $owner;

    private Store $storeOne;

    private Store $storeTwo;

    private ProductDefinition $cable;

    private ProductDefinition $usedPhone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOperationalRoles();
        $this->owner = $this->createOwner();
        $this->storeOne = Store::factory()->create(['code' => 'KT01', 'name' => 'Karman Telekom — Store 1']);
        $this->storeTwo = Store::factory()->create(['code' => 'KT02', 'name' => 'Karman Telekom — Store 2']);
        $this->cable = ProductDefinition::factory()->create(['name' => 'USB-C Cable', 'model' => 'C-100', 'tracking_mode' => TrackingMode::QUANTITY, 'sku' => 'CBL-100']);
        $this->usedPhone = ProductDefinition::factory()->create(['name' => 'iPhone 13', 'tracking_mode' => TrackingMode::USED_DEVICE]);
    }

    #[Test]
    public function owner_records_opening_stock_then_counts_the_shelf(): void
    {
        $this->actingAs($this->owner)
            ->get(route('subdomain.inventory.product', [$this->storeOne, $this->cable]))
            ->assertOk()
            ->assertSeeLivewire(ProductInventory::class)
            ->assertSee('dir="rtl" lang="ar"', false)
            ->assertSee(['الرصيد الافتتاحي', 'لا توجد حركات مخزون بعد.']);

        $component = Livewire::actingAs($this->owner)
            ->test(ProductInventory::class, ['store' => $this->storeOne, 'productDefinition' => $this->cable])
            ->set('openingQuantity', '10')
            ->call('recordOpeningBalance')
            ->assertHasNoErrors()
            ->assertDispatched('notify', ['type' => 'success', 'message' => 'تم تسجيل حركة المخزون.'])
            ->assertSee(['الجرد الفعلي', 'بدء الجرد'])
            ->assertDontSee('تسجيل الرصيد الافتتاحي');

        $component
            ->call('startCount')
            ->set('countedQuantity', '7')
            ->call('recordCount')
            ->assertHasErrors(['countReason' => 'required'])
            ->set('countReason', 'Crushed in delivery')
            ->call('recordCount')
            ->assertHasNoErrors()
            ->assertSet('countInProgress', false)
            ->assertViewHas('quantity', 7)
            ->assertSee(['رصيد افتتاحي', 'نقص', 'جرد فعلي', 'الرصيد السابق', 'Crushed in delivery']);

        $this->assertSame(2, InventoryMovement::count());

        $component
            ->call('startCount')
            ->set('countedQuantity', '7')
            ->set('countReason', 'Weekly count')
            ->call('recordCount')
            ->assertHasNoErrors()
            ->assertDispatched('notify', ['type' => 'info', 'message' => 'العدد يطابق الرصيد الحالي. لم يُسجَّل أي تغيير.']);

        $this->assertSame(2, InventoryMovement::count());

        $this->actingAs($this->owner)
            ->get(route('subdomain.inventory.store', $this->storeOne))
            ->assertOk()
            ->assertSeeLivewire(StoreInventory::class)
            ->assertSee(['USB-C Cable', 'C-100']);
    }

    #[Test]
    public function a_retried_submission_with_the_same_key_is_not_applied_twice(): void
    {
        $component = Livewire::actingAs($this->owner)
            ->test(ProductInventory::class, ['store' => $this->storeOne, 'productDefinition' => $this->cable]);

        $key = $component->get('submissionKey');
        $component->set('openingQuantity', '10')->call('recordOpeningBalance')->assertHasNoErrors();

        $this->assertNotSame($key, $component->get('submissionKey'));

        app(RecordOpeningBalance::class)($this->owner, $this->storeOne, $this->cable, 10, null, $key);

        $this->assertSame(1, InventoryMovement::count());
    }

    #[Test]
    public function owner_registers_views_and_removes_a_used_phone_and_its_imei_stays_reserved(): void
    {
        $imei = self::imei('35693803564380');

        Livewire::actingAs($this->owner)
            ->test(ProductInventory::class, ['store' => $this->storeOne, 'productDefinition' => $this->usedPhone])
            ->set('unitCondition', 'b')
            ->set('imei', '356938035643801')
            ->call('registerOpeningUnit')
            ->assertHasErrors('imei')
            ->assertSee('يجب أن يتكون IMEI من 15 رقمًا برقم تحقق صحيح.')
            ->set('imei', $imei)
            ->call('registerOpeningUnit')
            ->assertHasNoErrors()
            ->assertViewHas('availableUnits', 1)
            ->assertSee($imei);

        $unit = InventoryUnit::query()->sole();

        $this->actingAs($this->owner)
            ->get(route('subdomain.inventory.unit', [$this->storeOne, $unit]))
            ->assertOk()
            ->assertSeeLivewire(UnitDetail::class)
            ->assertSee([$imei, 'B', 'متاح', 'رصيد افتتاحي']);

        Livewire::actingAs($this->owner)
            ->test(UnitDetail::class, ['store' => $this->storeOne, 'unit' => $unit])
            ->set('removalReason', 'damaged')
            ->call('remove')
            ->assertHasErrors(['removalNote' => 'required'])
            ->set('removalNote', 'Board failure')
            ->call('remove')
            ->assertHasNoErrors()
            ->assertSee(['مُزال', 'تمت إزالة هذه الوحدة من المخزون.', 'Board failure'])
            ->assertDontSee('إزالة الوحدة');

        $this->assertSame(UnitStatus::REMOVED, $unit->fresh()->status);
        $this->assertSame($imei, $unit->identifiers()->value('value'));

        Livewire::actingAs($this->owner)
            ->test(ProductInventory::class, ['store' => $this->storeTwo, 'productDefinition' => $this->usedPhone])
            ->set('unitCondition', 'a')
            ->set('imei', $imei)
            ->call('registerOpeningUnit')
            ->assertHasErrors('imei')
            ->assertSee('رقم IMEI هذا مسجل مسبقًا لجهاز آخر.');

        $this->assertSame(1, InventoryUnit::count());
    }

    #[Test]
    public function a_unit_url_under_another_store_returns_not_found(): void
    {
        $unit = $this->registerPhone($this->storeOne);

        $this->actingAs($this->owner)
            ->get(route('subdomain.inventory.unit', [$this->storeOne, $unit]))
            ->assertOk();

        // The application's 404 page needs storefront data, so assert the routing failure itself.
        $this->withoutExceptionHandling();

        $this->assertThrows(
            fn () => $this->actingAs($this->owner)->get(route('subdomain.inventory.unit', [$this->storeTwo, $unit])),
            ModelNotFoundException::class,
        );
    }

    #[Test]
    public function a_unit_mounted_under_another_store_is_never_loaded(): void
    {
        $unit = $this->registerPhone($this->storeOne);

        $this->withoutExceptionHandling();

        $this->assertThrows(
            fn () => Livewire::actingAs($this->owner)->test(UnitDetail::class, ['store' => $this->storeTwo, 'unit' => $unit]),
            ModelNotFoundException::class,
        );
    }

    #[Test]
    public function locked_identifiers_cannot_be_tampered_with(): void
    {
        $unit = $this->registerPhone($this->storeOne);
        $otherUnit = $this->registerPhone($this->storeTwo, '35693803564381');

        $this->assertThrows(
            fn () => Livewire::actingAs($this->owner)
                ->test(UnitDetail::class, ['store' => $this->storeOne, 'unit' => $unit])
                ->set('unitId', $otherUnit->id),
            CannotUpdateLockedPropertyException::class,
        );

        $this->assertThrows(
            fn () => Livewire::actingAs($this->owner)
                ->test(ProductInventory::class, ['store' => $this->storeOne, 'productDefinition' => $this->cable])
                ->set('submissionKey', 'attacker-chosen'),
            CannotUpdateLockedPropertyException::class,
        );
    }

    /**
     * @return array<string, array{?string, ?string, int}>
     */
    public static function deniedVisitors(): array
    {
        return [
            'guest' => [null, null, 302],
            'employee' => ['employee', null, 302],
            'salesperson' => ['salesperson', null, 403],
            'owner without view_inventory' => ['admin', 'view_inventory', 403],
        ];
    }

    #[Test]
    #[DataProvider('deniedVisitors')]
    public function inventory_pages_are_owner_only_by_direct_url(?string $role, ?string $revoked, int $expectedStatus): void
    {
        $unit = $this->registerPhone($this->storeOne);

        if ($revoked !== null) {
            $this->revokeFromOwners($revoked);
        }

        if ($role !== null) {
            $user = User::factory()->create()->assignRole($role);
            $this->storeOne->employees()->attach($user);
            $this->actingAs($user);
        }

        $this->get(route('subdomain.inventory'))->assertStatus($expectedStatus);
        $this->get(route('subdomain.inventory.store', $this->storeOne))->assertStatus($expectedStatus);
        $this->get(route('subdomain.inventory.product', [$this->storeOne, $this->cable]))->assertStatus($expectedStatus);
        $this->get(route('subdomain.inventory.unit', [$this->storeOne, $unit]))->assertStatus($expectedStatus);
    }

    #[Test]
    public function revoking_permissions_blocks_later_livewire_requests(): void
    {
        $component = Livewire::actingAs($this->owner)
            ->test(ProductInventory::class, ['store' => $this->storeOne, 'productDefinition' => $this->cable]);

        $this->revokeFromOwners('adjust_inventory');

        $component->set('openingQuantity', '5')->call('recordOpeningBalance')->assertForbidden();
        $this->assertSame(0, InventoryMovement::count());

        $viewer = Livewire::actingAs($this->owner)
            ->test(StoreInventory::class, ['store' => $this->storeOne]);

        $this->revokeFromOwners('view_inventory');

        $viewer->set('search', 'cable')->assertForbidden();
    }

    #[Test]
    public function owner_without_adjust_permission_sees_read_only_inventory(): void
    {
        $this->revokeFromOwners('adjust_inventory');

        Livewire::actingAs($this->owner)
            ->test(ProductInventory::class, ['store' => $this->storeOne, 'productDefinition' => $this->cable])
            ->assertSee('يمكنك عرض المخزون فقط. لا تملك صلاحية تعديله.')
            ->assertDontSee('تسجيل الرصيد الافتتاحي');
    }

    #[Test]
    public function store_picker_lists_accessible_stores_with_stock_totals(): void
    {
        $this->registerPhone($this->storeOne);

        Livewire::actingAs($this->owner)
            ->test(InventoryStores::class)
            ->assertSee(['KT01', 'KT02', 'الوحدات المتاحة'])
            ->assertViewHas('stores', fn ($stores) => $stores->firstWhere('code', 'KT01')->available_units_count === 1);
    }

    #[Test]
    public function store_inventory_finds_catalog_products_by_name_or_exact_identifier(): void
    {
        $component = Livewire::actingAs($this->owner)
            ->test(StoreInventory::class, ['store' => $this->storeOne])
            ->assertSee('لا يوجد مخزون في هذا الفرع بعد');

        $component->set('catalogSearch', 'usb-c')->assertViewHas('catalogMatches', fn ($matches) => $matches->pluck('id')->all() === [$this->cable->id]);
        $component->set('catalogSearch', ' CBL-100 ')->assertViewHas('catalogMatches', fn ($matches) => $matches->pluck('id')->all() === [$this->cable->id]);
        $component->set('catalogSearch', 'CBL-10')->assertViewHas('catalogMatches', fn ($matches) => $matches->isEmpty());
    }

    #[Test]
    public function inventory_pages_render_left_to_right_in_english(): void
    {
        $this->useEnglishLocale();
        $unit = $this->registerPhone($this->storeOne);

        $this->actingAs($this->owner)
            ->get(route('subdomain.inventory.product', [$this->storeOne, $this->cable]))
            ->assertOk()
            ->assertSee('dir="ltr" lang="en"', false)
            ->assertSee(['Opening balance', 'Record opening balance', 'Movement history'])
            ->assertDontSee('الرصيد الافتتاحي');

        $this->actingAs($this->owner)
            ->get(route('subdomain.inventory.unit', [$this->storeOne, $unit]))
            ->assertOk()
            ->assertSee('dir="ltr" lang="en"', false)
            ->assertSee(['Available', 'Remove from stock', 'Opening balance']);
    }

    private function registerPhone(Store $store, string $imeiBody = '35693803564380'): InventoryUnit
    {
        return app(RegisterOpeningUnit::class)(
            $this->owner,
            $store,
            $this->usedPhone,
            UnitCondition::B,
            self::imei($imeiBody),
            null,
            null,
            null,
            uniqid('fixture-', true),
        )->unit;
    }
}
