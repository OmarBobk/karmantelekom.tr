<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Inventory\RecordOpeningBalance;
use App\Actions\Inventory\RecordStockCount;
use App\Actions\Inventory\RemoveUnit;
use App\Actions\Inventory\SetStockThreshold;
use App\Enums\AdjustmentReason;
use App\Enums\TrackingMode;
use App\Enums\UnitCondition;
use App\Enums\UnitStatus;
use App\Livewire\Backend\Inventory\ProductInventory;
use App\Livewire\Backend\Inventory\StoreInventory;
use App\Livewire\Backend\Reports\OperationsDashboard;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\InventoryStockThreshold;
use App\Models\ProductDefinition;
use App\Models\Store;
use App\Services\Reporting\LowStock;
use App\Services\Reporting\NeedsAttention;
use App\Services\Reporting\OutOfStock;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\InteractsWithPos;
use Tests\TestCase;

class LowStockVisibilityTest extends TestCase
{
    use InteractsWithPos;
    use RefreshDatabase;

    private Store $second;

    private ProductDefinition $serializedPhone;

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
    }

    #[Test]
    public function threshold_three_places_counts_in_disjoint_low_and_out_buckets(): void
    {
        $this->setThreshold($this->store, $this->cable, 3);

        $this->countTo($this->cable, 4, $this->store);
        $this->assertFalse(LowStock::definitions($this->store)->whereKey($this->cable->id)->exists());
        $this->assertFalse(OutOfStock::definitions($this->store)->whereKey($this->cable->id)->exists());

        $this->countTo($this->cable, 3, $this->store);
        $this->assertTrue(LowStock::definitions($this->store)->whereKey($this->cable->id)->exists());
        $this->assertFalse(OutOfStock::definitions($this->store)->whereKey($this->cable->id)->exists());

        $this->countTo($this->cable, 1, $this->store);
        $this->assertTrue(LowStock::definitions($this->store)->whereKey($this->cable->id)->exists());
        $this->assertFalse(OutOfStock::definitions($this->store)->whereKey($this->cable->id)->exists());

        $this->countTo($this->cable, 0, $this->store);
        $this->assertFalse(LowStock::definitions($this->store)->whereKey($this->cable->id)->exists());
        $this->assertTrue(OutOfStock::definitions($this->store)->whereKey($this->cable->id)->exists());
    }

    #[Test]
    public function same_product_can_be_low_at_one_branch_and_unset_or_healthy_at_another(): void
    {
        app(RecordOpeningBalance::class)($this->owner, $this->second, $this->cable, 2, null, (string) Str::uuid());
        $this->setThreshold($this->store, $this->cable, 3);
        $this->countTo($this->cable, 2, $this->store);

        $this->assertTrue(LowStock::definitions($this->store)->whereKey($this->cable->id)->exists());
        $this->assertFalse(LowStock::definitions($this->second)->whereKey($this->cable->id)->exists());

        $this->setThreshold($this->second, $this->cable, 1);
        $this->assertFalse(LowStock::definitions($this->second)->whereKey($this->cable->id)->exists());

        $this->countTo($this->cable, 1, $this->second);
        $this->assertTrue(LowStock::definitions($this->second)->whereKey($this->cable->id)->exists());
    }

    #[Test]
    public function unset_and_clear_remove_the_low_warning_without_changing_stock(): void
    {
        $this->countTo($this->cable, 2, $this->store);

        $this->assertFalse(LowStock::definitions($this->store)->whereKey($this->cable->id)->exists());

        $this->setThreshold($this->store, $this->cable, 3);
        $this->assertTrue(LowStock::definitions($this->store)->whereKey($this->cable->id)->exists());

        $movementsBefore = InventoryMovement::query()
            ->where('store_id', $this->store->id)
            ->where('product_definition_id', $this->cable->id)
            ->count();
        $balanceBefore = InventoryBalance::query()
            ->where('store_id', $this->store->id)
            ->where('product_definition_id', $this->cable->id)
            ->value('quantity');

        Livewire::actingAs($this->owner)
            ->test(ProductInventory::class, ['store' => $this->store, 'productDefinition' => $this->cable])
            ->call('clearStockThreshold')
            ->assertSee('Not set')
            ->assertSet('stockThreshold', '');

        $this->assertFalse(LowStock::definitions($this->store)->whereKey($this->cable->id)->exists());
        $this->assertSame(2, $this->cableStock());
        $this->assertSame($balanceBefore, InventoryBalance::query()
            ->where('store_id', $this->store->id)
            ->where('product_definition_id', $this->cable->id)
            ->value('quantity'));
        $this->assertSame($movementsBefore, InventoryMovement::query()
            ->where('store_id', $this->store->id)
            ->where('product_definition_id', $this->cable->id)
            ->count());
        $this->assertDatabaseMissing('inventory_stock_thresholds', [
            'store_id' => $this->store->id,
            'product_definition_id' => $this->cable->id,
        ]);
    }

    #[Test]
    public function serialized_available_units_drive_low_stock_and_used_devices_are_excluded(): void
    {
        $this->setThreshold($this->store, $this->serializedPhone, 1);

        $first = $this->registerDevice(
            $this->owner,
            $this->store,
            $this->serializedPhone,
            self::imei('35693803564422'),
            'SN-PIX-1',
            null,
            UnitCondition::NEW,
        );
        $second = $this->registerDevice(
            $this->owner,
            $this->store,
            $this->serializedPhone,
            self::imei('35693803564430'),
            'SN-PIX-2',
            null,
            UnitCondition::NEW,
        );

        $this->assertFalse(LowStock::definitions($this->store)->whereKey($this->serializedPhone->id)->exists());

        app(RemoveUnit::class)(
            $this->owner,
            $this->store,
            $second->id,
            AdjustmentReason::LOST,
            'Moved',
            (string) Str::uuid(),
            $second->lifecycle_revision,
        );

        $this->assertTrue(LowStock::definitions($this->store)->whereKey($this->serializedPhone->id)->exists());

        $first->update(['status' => UnitStatus::IN_REPAIR]);
        $this->assertFalse(LowStock::definitions($this->store)->whereKey($this->serializedPhone->id)->exists());
        $this->assertTrue(OutOfStock::definitions($this->store)->whereKey($this->serializedPhone->id)->exists());

        InventoryStockThreshold::query()->create([
            'store_id' => $this->store->id,
            'product_definition_id' => $this->usedPhone->id,
            'threshold' => 5,
        ]);
        $this->assertFalse(LowStock::definitions($this->store)->whereKey($this->usedPhone->id)->exists());

        try {
            app(SetStockThreshold::class)($this->owner, $this->store, $this->usedPhone, '2');
            $this->fail('Used-device thresholds must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('threshold', $exception->errors());
        }
    }

    #[Test]
    public function dashboard_and_needs_attention_match_filtered_list_and_preserve_out_filter(): void
    {
        $this->setThreshold($this->store, $this->cable, 3);
        $this->countTo($this->cable, 2, $this->store);

        $outCable = ProductDefinition::factory()->priced('9.90')->create([
            'name' => 'Empty adapter',
            'tracking_mode' => TrackingMode::QUANTITY,
        ]);
        app(RecordOpeningBalance::class)($this->owner, $this->store, $outCable, 1, null, (string) Str::uuid());
        $this->countTo($outCable, 0, $this->store);

        $dashboard = Livewire::actingAs($this->owner)
            ->withQueryParams(['from' => '2026-11-10', 'to' => '2026-11-10'])
            ->test(OperationsDashboard::class);

        $inventory = $dashboard->viewData('inventory')->keyBy('id');
        $this->assertSame(1, $inventory[$this->store->id]->low_stock_count);
        $this->assertSame(1, $inventory[$this->store->id]->out_of_stock_count);
        $dashboard
            ->assertSee(route('subdomain.inventory.store', [$this->store, 'stock' => 'low']))
            ->assertSee(route('subdomain.inventory.store', [$this->store, 'stock' => 'out']));

        $attention = collect(NeedsAttention::for(
            $this->owner,
            Store::query()->whereKey($this->store->id)->get(),
        ));
        $this->assertTrue($attention->contains(fn (array $item): bool => $item['key'] === 'low-stock-'.$this->store->id));
        $this->assertTrue($attention->contains(fn (array $item): bool => $item['key'] === 'out-of-stock-'.$this->store->id));

        $lowList = Livewire::actingAs($this->owner)
            ->withQueryParams(['stock' => 'low'])
            ->test(StoreInventory::class, ['store' => $this->store]);

        $this->assertSame([$this->cable->id], $lowList->viewData('products')->pluck('id')->all());
        $lowList->assertSee('Low stock')->assertSee($this->cable->name)->assertSee('2')->assertSee('3')->assertDontSee($outCable->name);

        $outList = Livewire::actingAs($this->owner)
            ->withQueryParams(['stock' => 'out'])
            ->test(StoreInventory::class, ['store' => $this->store]);

        $this->assertSame([$outCable->id], $outList->viewData('products')->pluck('id')->all());
        $outList->assertSee('Out of stock')->assertSee($outCable->name)->assertDontSee($this->cable->name);

        Livewire::actingAs($this->owner)
            ->test(ProductInventory::class, ['store' => $this->store, 'productDefinition' => $this->cable])
            ->set('stockThreshold', '0')
            ->call('saveStockThreshold')
            ->assertHasErrors(['stockThreshold']);

        Livewire::actingAs($this->owner)
            ->test(ProductInventory::class, ['store' => $this->store, 'productDefinition' => $this->cable])
            ->set('stockThreshold', '1.5')
            ->call('saveStockThreshold')
            ->assertHasErrors(['stockThreshold']);

        $this->setThreshold($this->store, $this->cable, 3);

        Livewire::actingAs($this->owner)
            ->test(ProductInventory::class, ['store' => $this->store, 'productDefinition' => $this->cable])
            ->set('stockThreshold', '4294967296')
            ->call('saveStockThreshold')
            ->assertHasErrors(['stockThreshold'])
            ->assertSee(__('inventory.threshold.errors.too_large', ['max' => SetStockThreshold::MAX_THRESHOLD]));

        $this->assertDatabaseHas('inventory_stock_thresholds', [
            'store_id' => $this->store->id,
            'product_definition_id' => $this->cable->id,
            'threshold' => 3,
        ]);

        Livewire::actingAs($this->owner)
            ->test(ProductInventory::class, ['store' => $this->store, 'productDefinition' => $this->cable])
            ->set('stockThreshold', '5')
            ->call('saveStockThreshold')
            ->assertHasNoErrors()
            ->assertSeeHtml('data-testid="threshold-current"')
            ->assertSee('5');

        $this->assertDatabaseHas('inventory_stock_thresholds', [
            'store_id' => $this->store->id,
            'product_definition_id' => $this->cable->id,
            'threshold' => 5,
        ]);

        $this->revokeFromOwners('manage_purchases');

        Livewire::actingAs($this->owner)
            ->test(ProductInventory::class, ['store' => $this->store, 'productDefinition' => $this->cable])
            ->set('stockThreshold', '4')
            ->call('saveStockThreshold')
            ->assertForbidden();

        try {
            app(SetStockThreshold::class)($this->createCashier(), $this->store, $this->cable, '2');
            $this->fail('Cashiers must not set thresholds.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        config(['app.locale' => 'ar']);
        App::setLocale('ar');

        Livewire::actingAs($this->owner)
            ->withQueryParams(['stock' => 'low'])
            ->test(StoreInventory::class, ['store' => $this->store])
            ->assertSee('مخزون منخفض')
            ->assertSeeHtml('dir="rtl"')
            ->assertSeeHtml('lang="ar"');

        Livewire::actingAs($this->owner)
            ->test(ProductInventory::class, ['store' => $this->store, 'productDefinition' => $this->cable])
            ->assertSee('حد المخزون المنخفض')
            ->assertSeeHtml('dir="rtl"')
            ->assertSeeHtml('data-testid="stock-threshold"');
    }

    private function setThreshold(Store $store, ProductDefinition $definition, int $threshold): InventoryStockThreshold
    {
        return app(SetStockThreshold::class)($this->owner, $store, $definition, (string) $threshold);
    }

    private function countTo(ProductDefinition $definition, int $quantity, Store $store): void
    {
        app(RecordStockCount::class)(
            $this->owner,
            $store,
            $definition,
            $quantity,
            InventoryMovement::query()
                ->where('store_id', $store->id)
                ->where('product_definition_id', $definition->id)
                ->max('id'),
            'Counted to '.$quantity,
            (string) Str::uuid(),
        );
    }
}
