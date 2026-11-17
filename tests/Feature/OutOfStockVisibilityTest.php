<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Inventory\RecordOpeningBalance;
use App\Actions\Inventory\RecordStockCount;
use App\Actions\Inventory\RemoveUnit;
use App\Enums\AdjustmentReason;
use App\Enums\TrackingMode;
use App\Enums\UnitCondition;
use App\Enums\UnitStatus;
use App\Livewire\Backend\Inventory\StoreInventory;
use App\Livewire\Backend\Reports\OperationsDashboard;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\ProductDefinition;
use App\Models\Store;
use App\Services\Reporting\OutOfStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\InteractsWithPos;
use Tests\TestCase;

class OutOfStockVisibilityTest extends TestCase
{
    use InteractsWithPos;
    use RefreshDatabase;

    private Store $second;

    private ProductDefinition $serializedPhone;

    private ProductDefinition $neverStocked;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCounter(10);
        $this->useEnglishLocale();
        $this->second = Store::factory()->create(['code' => 'KT02', 'name' => 'Karman Aksaray']);

        $this->serializedPhone = ProductDefinition::factory()->priced('8999.00')->create([
            'name' => 'Pixel 8',
            'tracking_mode' => TrackingMode::SERIALIZED,
            'sku' => 'PIX-8-NEW',
        ]);
        $this->neverStocked = ProductDefinition::factory()->priced('19.90')->create([
            'name' => 'Never stocked adapter',
            'tracking_mode' => TrackingMode::QUANTITY,
            'sku' => 'ADP-NEVER',
        ]);
    }

    #[Test]
    public function out_of_stock_includes_zero_quantity_and_serialized_without_available_units_and_excludes_others(): void
    {
        $this->countToZero($this->cable);

        $serialized = $this->registerDevice(
            $this->owner,
            $this->store,
            $this->serializedPhone,
            self::imei('35693803564422'),
            'SN-PIX-1',
            null,
            UnitCondition::NEW,
        );
        app(RemoveUnit::class)(
            $this->owner,
            $this->store,
            $serialized->id,
            AdjustmentReason::LOST,
            'Sold elsewhere',
            (string) Str::uuid(),
            $serialized->lifecycle_revision,
        );

        $stillAvailable = $this->registerDevice(
            $this->owner,
            $this->store,
            ProductDefinition::factory()->priced('100.00')->create([
                'name' => 'In-stock serial',
                'tracking_mode' => TrackingMode::SERIALIZED,
            ]),
            self::imei('35693803564430'),
            'SN-OK',
            null,
            UnitCondition::NEW,
        );

        $inactive = ProductDefinition::factory()->priced('5.00')->create([
            'name' => 'Inactive cable',
            'tracking_mode' => TrackingMode::QUANTITY,
            'is_active' => true,
        ]);
        app(RecordOpeningBalance::class)($this->owner, $this->store, $inactive, 3, null, (string) Str::uuid());
        $this->countToZero($inactive);
        $inactive->update(['is_active' => false]);

        app(RecordOpeningBalance::class)($this->owner, $this->second, $this->cable, 4, null, (string) Str::uuid());

        $ids = OutOfStock::definitions($this->store)->orderBy('id')->pluck('id')->all();

        $this->assertSame([$this->cable->id, $this->serializedPhone->id], $ids);
        $this->assertNotContains($this->neverStocked->id, $ids);
        $this->assertNotContains($inactive->id, $ids);
        $this->assertNotContains($this->usedPhone->id, $ids);
        $this->assertNotContains($stillAvailable->product_definition_id, $ids);
        $this->assertSame([], OutOfStock::definitions($this->second)->pluck('id')->all());
        $this->assertSame([
            $this->store->id => 2,
            $this->second->id => 0,
        ], OutOfStock::countsByStore([$this->store->id, $this->second->id]));
    }

    #[Test]
    public function physical_quantity_not_sellable_after_holds_decides_out_of_stock(): void
    {
        InventoryBalance::query()
            ->where('store_id', $this->store->id)
            ->where('product_definition_id', $this->cable->id)
            ->update(['quantity' => 5]);

        $this->assertFalse(
            OutOfStock::definitions($this->store)->whereKey($this->cable->id)->exists(),
            'On-hand quantity above zero stays in stock even if every unit were held for online orders.',
        );

        $this->countToZero($this->cable);

        $this->assertTrue(OutOfStock::definitions($this->store)->whereKey($this->cable->id)->exists());
    }

    #[Test]
    public function dashboard_counts_match_inventory_filter_and_employees_are_refused(): void
    {
        $this->countToZero($this->cable);

        $dashboard = Livewire::actingAs($this->owner)
            ->withQueryParams(['from' => '2026-11-10', 'to' => '2026-11-10'])
            ->test(OperationsDashboard::class);

        $inventory = $dashboard->viewData('inventory')->keyBy('id');
        $this->assertSame(1, $inventory[$this->store->id]->out_of_stock_count);
        $dashboard->assertSee(route('subdomain.inventory.store', [$this->store, 'stock' => 'out']));

        $list = Livewire::actingAs($this->owner)
            ->withQueryParams(['stock' => 'out'])
            ->test(StoreInventory::class, ['store' => $this->store]);

        $this->assertSame([$this->cable->id], $list->viewData('products')->pluck('id')->all());
        $list->assertSee('Out of stock')->assertSee($this->cable->name)->assertDontSee($this->neverStocked->name);

        Livewire::actingAs($this->owner)
            ->withQueryParams(['stock' => 'forged'])
            ->test(StoreInventory::class, ['store' => $this->store])
            ->assertSet('stock', '')
            ->assertDontSee(__('inventory.store.out_of_stock_filter'));

        Livewire::actingAs($this->createCashier())->test(StoreInventory::class, ['store' => $this->store])->assertForbidden();
    }

    #[Test]
    public function serialized_presence_follows_branch_movements_not_only_current_unit_store(): void
    {
        $unit = $this->registerDevice(
            $this->owner,
            $this->store,
            $this->serializedPhone,
            self::imei('35693803564448'),
            'SN-MOVE',
            null,
            UnitCondition::NEW,
        );

        $unit->update(['store_id' => $this->second->id, 'status' => UnitStatus::AVAILABLE]);

        $this->assertTrue(OutOfStock::definitions($this->store)->whereKey($this->serializedPhone->id)->exists());
        $this->assertFalse(OutOfStock::definitions($this->second)->whereKey($this->serializedPhone->id)->exists());
    }

    private function countToZero(ProductDefinition $definition): void
    {
        app(RecordStockCount::class)(
            $this->owner,
            $this->store,
            $definition,
            0,
            InventoryMovement::query()
                ->where('store_id', $this->store->id)
                ->where('product_definition_id', $definition->id)
                ->max('id'),
            'Counted empty',
            (string) Str::uuid(),
        );
    }
}
