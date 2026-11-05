<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Inventory\CloseRepair;
use App\Actions\Inventory\DeviceCostInput;
use App\Actions\Inventory\RecordDeviceCost;
use App\Enums\InspectionOutcome;
use App\Enums\RepairerType;
use App\Enums\RepairStatus;
use App\Enums\TrackingMode;
use App\Enums\UnitStatus;
use App\Livewire\Backend\Inventory\ProductInventory;
use App\Livewire\Backend\Inventory\UnitDetail;
use App\Models\DeviceCostEntry;
use App\Models\DeviceInspection;
use App\Models\DeviceRepair;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\ProductDefinition;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\InteractsWithDeviceLifecycle;
use Tests\Support\InteractsWithOperationalInventory;
use Tests\TestCase;

class DeviceLifecycleUiTest extends TestCase
{
    use InteractsWithDeviceLifecycle;
    use InteractsWithOperationalInventory;
    use RefreshDatabase;

    private User $owner;

    private Store $store;

    private ProductDefinition $usedPhone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOperationalRoles();
        $this->owner = $this->createOwner();
        $this->store = Store::factory()->create(['code' => 'KT01', 'name' => 'Karman Telekom — Store 1']);
        $this->usedPhone = ProductDefinition::factory()->create(['name' => 'iPhone 13', 'model' => 'A2633', 'tracking_mode' => TrackingMode::USED_DEVICE]);
    }

    #[Test]
    public function an_operator_walks_a_device_from_inspection_through_repair_back_to_stock_in_arabic(): void
    {
        $imei = self::imei('35693803564380');
        $unit = $this->registerDevice($this->owner, $this->store, $this->usedPhone, $imei);

        $this->actingAs($this->owner)->get(route('subdomain.inventory.unit', [$this->store, $unit]))
            ->assertOk()
            ->assertSee(['dir="rtl" lang="ar"', 'inputmode="decimal"'], false)
            ->assertSee(['iPhone 13', 'A2633', $imei, 'متاح', 'KT01', 'في الفرع', 'تسجيل فحص', 'غير معروف — سُجّل دون استلام مشتريات'])
            ->assertDontSee('بدء الصيانة</button>', false);

        $page = Livewire::actingAs($this->owner)->test(UnitDetail::class, ['store' => $this->store, 'unit' => $unit]);

        $page->set('inspectionOutcome', 'needs_repair')
            ->call('recordInspection')
            ->assertHasErrors('inspectionFindings')
            ->assertSee('حقل الملاحظات الفنية مطلوب.')
            ->set('inspectionFindings', 'شاشة تومض')
            ->call('recordInspection')
            ->assertHasNoErrors()
            ->assertDispatched('close-dialog', id: 'record-inspection')
            ->assertSet('expectedStatus', 'inspection')
            ->assertSee(['بانتظار الفحص', 'شاشة تومض', 'بدء الصيانة']);

        $page->set('repairerType', 'external')
            ->set('repairProblem', 'Cracked screen')
            ->call('startRepair')
            ->assertHasErrors('repairerName')
            ->assertSet('repairProblem', 'Cracked screen')
            ->set('repairerName', 'Fix Shop')
            ->call('startRepair')
            ->assertHasNoErrors()
            ->assertSet('expectedStatus', 'in_repair')
            ->assertSee(['قيد الصيانة', 'لدى', 'Fix Shop', 'إكمال الصيانة', 'إلغاء الصيانة'])
            ->assertDontSee('إزالة الوحدة');

        $page->set('completionWork', 'Replaced screen')
            ->set('partsMode', 'known')
            ->set('partsAmount', '1.234')
            ->set('partsCurrency', 'TRY')
            ->set('partsDescription', 'Screen')
            ->set('laborMode', 'unknown')
            ->call('completeRepair')
            ->assertHasErrors(['partsAmount'])
            ->assertSet('partsDescription', 'Screen')
            ->set('partsAmount', '1250.50')
            ->call('completeRepair')
            ->assertHasErrors(['completionReturned'])
            ->assertSet('expectedStatus', 'in_repair')
            ->set('completionReturned', true)
            ->call('completeRepair')
            ->assertHasNoErrors()
            ->assertSet('expectedStatus', 'inspection')
            ->assertSee(['1,250.50', 'TRY', 'غير معروف']);

        $this->assertSame(2, DeviceCostEntry::count());
        $this->assertSame(RepairStatus::COMPLETED, DeviceRepair::query()->sole()->status);

        $page->set('inspectionOutcome', 'ready')
            ->set('inspectionCondition', 'for_parts')
            ->call('recordInspection')
            ->assertHasErrors('inspectionCondition')
            ->set('inspectionCondition', 'a')
            ->call('recordInspection')
            ->assertHasNoErrors()
            ->assertSet('expectedStatus', 'available')
            ->assertSee(['متاح للبيع', 'إكمال صيانة', 'بدء صيانة']);

        $this->assertSame(UnitStatus::AVAILABLE, $unit->fresh()->status);
        $this->assertSame(4, $unit->fresh()->lifecycle_revision);
    }

    #[Test]
    public function the_device_page_renders_in_english_ltr(): void
    {
        $this->useEnglishLocale();
        $unit = $this->deviceNeedingRepair($this->owner, $this->store, $this->usedPhone, self::imei('35693803564380'));

        $this->actingAs($this->owner)->get(route('subdomain.inventory.unit', [$this->store, $unit]))
            ->assertOk()
            ->assertSee('dir="ltr" lang="en"', false)
            ->assertSee(['Awaiting inspection', 'Start repair', 'Inspections', 'Repairs', 'Device costs', 'Device timeline', 'Screen flicker', 'Remove from stock']);
    }

    #[Test]
    public function a_stale_form_keeps_its_input_and_stays_bound_to_the_old_state_until_the_operator_continues(): void
    {
        $this->useEnglishLocale();
        $unit = $this->deviceNeedingRepair($this->owner, $this->store, $this->usedPhone, self::imei('35693803564380'));

        $page = Livewire::actingAs($this->owner)
            ->test(UnitDetail::class, ['store' => $this->store, 'unit' => $unit])
            ->set('inspectionOutcome', 'ready')
            ->set('inspectionCondition', 'a')
            ->set('inspectionFindings', 'Looks fine now');

        $this->startDeviceRepair($this->owner, $unit);
        $this->closeDeviceRepair($this->owner, $unit, RepairStatus::CANCELLED);

        foreach ([1, 2] as $attempt) {
            $page->call('recordInspection')
                ->assertHasErrors('inspectionOutcome')
                ->assertSet('inspectionFindings', 'Looks fine now')
                ->assertSet('inspectionCondition', 'a')
                ->assertSet('expectedRevision', 1)
                ->assertNotDispatched('close-dialog')
                ->assertSee(['This device changed', 'Nothing was saved', 'Continue with current state']);
        }

        $this->assertSame(UnitStatus::INSPECTION, $unit->fresh()->status);
        $this->assertSame(1, DeviceInspection::count());

        $page->call('refreshState')
            ->assertSet('expectedRevision', 3)
            ->assertSet('inspectionFindings', 'Looks fine now')
            ->assertDontSee('This form was opened for an earlier state of the device');

        $page->call('recordInspection')->assertHasNoErrors()->assertSet('expectedStatus', 'available');
    }

    #[Test]
    public function an_old_repair_completion_form_cannot_close_a_newer_repair_or_attach_costs_to_it(): void
    {
        $this->useEnglishLocale();
        $unit = $this->deviceNeedingRepair($this->owner, $this->store, $this->usedPhone, self::imei('35693803564380'));
        $this->startDeviceRepair($this->owner, $unit, RepairerType::EXTERNAL, 'First Shop');
        $firstRepair = DeviceRepair::query()->sole();

        $page = Livewire::actingAs($this->owner)
            ->test(UnitDetail::class, ['store' => $this->store, 'unit' => $unit->fresh()])
            ->set('completionWork', 'Replaced screen')
            ->set('completionReturned', true)
            ->set('partsMode', 'known')
            ->set('partsAmount', '100')
            ->set('partsCurrency', 'TRY')
            ->set('partsDescription', 'Screen');

        $this->closeDeviceRepair($this->owner, $unit);
        $this->startDeviceRepair($this->owner, $unit, RepairerType::EXTERNAL, 'Second Shop');
        $secondRepair = DeviceRepair::query()->whereKeyNot($firstRepair->id)->sole();

        foreach ([1, 2] as $attempt) {
            $page->call('completeRepair')
                ->assertHasErrors('completionWork')
                ->assertNotDispatched('close-dialog')
                ->assertSet('expectedRevision', 2)
                ->assertSet('completionWork', 'Replaced screen')
                ->assertSet('partsAmount', '100')
                ->assertSee(['This form was opened for an earlier state of the device', 'Current open repair: #'.$secondRepair->id, 'Second Shop']);
        }

        $this->assertSame(RepairStatus::OPEN, $secondRepair->fresh()->status);
        $this->assertSame(UnitStatus::IN_REPAIR, $unit->fresh()->status);
        $this->assertSame(4, $unit->fresh()->lifecycle_revision);
        $this->assertSame(0, DeviceCostEntry::count());

        $page->call('refreshState')
            ->assertSet('expectedRevision', 4)
            ->assertSet('completionReturned', false)
            ->assertSet('completionWork', 'Replaced screen')
            ->assertSet('partsAmount', '100')
            ->call('completeRepair')
            ->assertHasErrors('completionReturned');

        $this->assertSame(RepairStatus::OPEN, $secondRepair->fresh()->status);

        $page->set('completionReturned', true)
            ->call('completeRepair')
            ->assertHasNoErrors()
            ->assertDispatched('close-dialog', id: 'complete-repair');

        $this->assertSame(RepairStatus::COMPLETED, $secondRepair->fresh()->status);
        $this->assertSame($secondRepair->id, DeviceCostEntry::query()->sole()->device_repair_id);
    }

    #[Test]
    public function a_completion_whose_response_was_lost_is_replayed_instead_of_repeated(): void
    {
        $this->useEnglishLocale();
        $unit = $this->deviceNeedingRepair($this->owner, $this->store, $this->usedPhone, self::imei('35693803564380'));
        $this->startDeviceRepair($this->owner, $unit);

        $page = Livewire::actingAs($this->owner)
            ->test(UnitDetail::class, ['store' => $this->store, 'unit' => $unit->fresh()])
            ->set('completionWork', 'Replaced screen')
            ->set('completionReturned', true)
            ->set('partsMode', 'known')
            ->set('partsAmount', '100')
            ->set('partsCurrency', 'TRY')
            ->set('partsDescription', 'Screen');

        app(CloseRepair::class)(
            $this->owner,
            $this->store,
            $unit->id,
            UnitStatus::IN_REPAIR,
            2,
            RepairStatus::COMPLETED,
            'Replaced screen',
            true,
            [DeviceCostInput::fromInput('repair_parts', 'Screen', '100', 'TRY', false, 'parts_')],
            $page->get('submissionKey'),
        );

        $page->call('completeRepair')
            ->assertHasNoErrors()
            ->assertDispatched('close-dialog', id: 'complete-repair')
            ->assertDispatched('notify', fn (string $event, array $params): bool => ($params[0]['type'] ?? null) === 'info')
            ->assertSet('expectedStatus', 'inspection');

        $this->assertSame(RepairStatus::COMPLETED, DeviceRepair::query()->sole()->status);
        $this->assertSame(1, DeviceCostEntry::count());
        $this->assertSame(1, InventoryMovement::query()->where('type', 'repair_completed')->count());
    }

    #[Test]
    public function a_stale_removal_form_is_rejected_even_when_the_status_matches_again(): void
    {
        $unit = $this->deviceNeedingRepair($this->owner, $this->store, $this->usedPhone, self::imei('35693803564380'));
        $page = Livewire::actingAs($this->owner)
            ->test(UnitDetail::class, ['store' => $this->store, 'unit' => $unit])
            ->set('removalReason', 'damaged')
            ->set('removalNote', 'Beyond repair');

        $this->startDeviceRepair($this->owner, $unit);
        $this->closeDeviceRepair($this->owner, $unit, RepairStatus::CANCELLED);

        $page->call('remove')->assertHasErrors('removalReason')->assertSet('removalNote', 'Beyond repair');
        $this->assertSame(UnitStatus::INSPECTION, $unit->fresh()->status);
    }

    #[Test]
    public function actions_follow_permissions_and_tampered_state_is_refused(): void
    {
        $this->useEnglishLocale();
        $unit = $this->deviceNeedingRepair($this->owner, $this->store, $this->usedPhone, self::imei('35693803564380'));
        $this->revokeFromOwners('service_devices');

        Livewire::actingAs($this->owner)
            ->test(UnitDetail::class, ['store' => $this->store, 'unit' => $unit])
            ->assertDontSee(['Record inspection', 'Start repair'])
            ->set('repairerName', 'Shop')
            ->set('repairProblem', 'Cracked')
            ->call('startRepair')
            ->assertForbidden();

        $this->assertSame(0, DeviceRepair::count());

        foreach (['expectedRevision' => 99, 'expectedStatus' => 'available', 'unitId' => 999] as $property => $value) {
            try {
                Livewire::actingAs($this->owner)->test(UnitDetail::class, ['store' => $this->store, 'unit' => $unit])->set($property, $value);
                $this->fail("{$property} must be locked.");
            } catch (CannotUpdateLockedPropertyException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->withoutExceptionHandling();
        $this->assertThrows(
            fn () => $this->actingAs($this->owner)->get(route('subdomain.inventory.unit', [Store::factory()->create(), $unit])),
            ModelNotFoundException::class,
        );
    }

    #[Test]
    public function the_timeline_is_paginated_with_a_fixed_number_of_queries(): void
    {
        $small = $this->deviceWithHistory(self::imei('35693803564380'), 2);
        $large = $this->deviceWithHistory(self::imei('35693803564381'), 12);

        $this->queriesForUnitPage($small);
        $smallQueries = $this->queriesForUnitPage($small);
        $largeQueries = $this->queriesForUnitPage($large);

        $this->assertSame($smallQueries, $largeQueries, 'Unit page queries must not grow with history length.');

        Livewire::actingAs($this->owner)
            ->test(UnitDetail::class, ['store' => $this->store, 'unit' => $large])
            ->assertViewHas('timeline', fn ($timeline) => $timeline->count() === UnitDetail::TIMELINE_PER_PAGE && $timeline->total() > UnitDetail::TIMELINE_PER_PAGE);
    }

    #[Test]
    public function product_history_lists_stock_movements_only_and_offers_an_in_service_filter(): void
    {
        $this->useEnglishLocale();
        $unit = $this->deviceNeedingRepair($this->owner, $this->store, $this->usedPhone, self::imei('35693803564380'));
        $this->registerDevice($this->owner, $this->store, $this->usedPhone, self::imei('35693803564381'));

        Livewire::actingAs($this->owner)
            ->test(ProductInventory::class, ['store' => $this->store, 'productDefinition' => $this->usedPhone])
            ->assertViewHas('movements', fn ($movements) => $movements->total() === 2)
            ->assertViewHas('availableUnits', 1)
            ->assertSee('Inspection &amp; repair', false)
            ->set('unitStatusFilter', 'in_service')
            ->assertViewHas('units', fn ($units) => $units->pluck('id')->all() === [$unit->id]);
    }

    private function deviceWithHistory(string $imei, int $cycles): InventoryUnit
    {
        $unit = $this->deviceNeedingRepair($this->owner, $this->store, $this->usedPhone, $imei);

        for ($cycle = 0; $cycle < $cycles; $cycle++) {
            $this->startDeviceRepair($this->owner, $unit);
            $this->closeDeviceRepair($this->owner, $unit, costs: [DeviceCostInput::fromInput('repair_labor', null, '10', 'TRY', false)]);
            app(RecordDeviceCost::class)($this->owner, $this->store, $unit->id, DeviceCostInput::fromInput('other', 'Courier', null, null, true), null, "cost-{$imei}-{$cycle}");
        }

        $this->inspectDevice($this->owner, $unit, InspectionOutcome::NEEDS_REPAIR, findings: 'Still faulty');

        return $unit->refresh();
    }

    private function queriesForUnitPage(InventoryUnit $unit): int
    {
        $this->actingAs($this->owner);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('subdomain.inventory.unit', [$this->store, $unit]))->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
