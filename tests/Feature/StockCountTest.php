<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Inventory\RecordOpeningBalance;
use App\Actions\Inventory\RecordStockCount;
use App\Enums\AdjustmentReason;
use App\Enums\MovementType;
use App\Enums\TrackingMode;
use App\Livewire\Backend\Inventory\ProductInventory;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\ProductDefinition;
use App\Models\Store;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\InteractsWithOperationalInventory;
use Tests\TestCase;

class StockCountTest extends TestCase
{
    use InteractsWithOperationalInventory;
    use RefreshDatabase;

    private User $owner;

    private Store $store;

    private ProductDefinition $cable;

    private InventoryMovement $opening;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOperationalRoles();
        $this->owner = $this->createOwner();
        $this->store = Store::factory()->create(['code' => 'KT01']);
        $this->cable = ProductDefinition::factory()->create(['tracking_mode' => TrackingMode::QUANTITY]);
        $this->opening = app(RecordOpeningBalance::class)($this->owner, $this->store, $this->cable, 8, null, 'open');
    }

    #[Test]
    public function counting_10_against_a_stable_8_records_plus_2_with_its_prior_balance(): void
    {
        $movement = $this->recordCount(10, $this->opening->id, 'Box found behind the counter');

        $this->assertSame(10, $this->balance());
        $this->assertSame(MovementType::ADJUSTMENT_INCREASE, $movement->type);
        $this->assertSame(AdjustmentReason::PHYSICAL_COUNT, $movement->reason);
        $this->assertSame(2, $movement->quantity_change);
        $this->assertSame(10, $movement->quantity_after);
        $this->assertSame(8, $movement->quantity_after - $movement->quantity_change);
        $this->assertSame('Box found behind the counter', $movement->note);
        $this->assertSame($this->owner->id, $movement->user_id);
        $this->assertSame($this->store->id, $movement->store_id);
    }

    #[Test]
    public function a_count_of_zero_empties_the_shelf_and_a_matching_count_writes_nothing(): void
    {
        $movement = $this->recordCount(0, $this->opening->id, 'Shelf empty');

        $this->assertSame(0, $this->balance());
        $this->assertSame(MovementType::ADJUSTMENT_DECREASE, $movement->type);
        $this->assertSame(-8, $movement->quantity_change);
        $this->assertSame(0, $movement->quantity_after);

        $this->assertNull($this->recordCount(0, $movement->id, 'Recount', 'recount'));
        $this->assertSame(2, InventoryMovement::count());
    }

    #[Test]
    public function a_count_is_stale_after_any_movement_even_when_the_balance_returned_to_the_same_number(): void
    {
        $countStartedAfter = $this->opening->id;

        $down = $this->recordCount(7, $countStartedAfter, 'Other tab', 'other-a');
        $this->recordCount(8, $down->id, 'Other tab', 'other-b');
        $this->assertSame(8, $this->balance());

        try {
            $this->recordCount(10, $countStartedAfter, 'Original count', 'original');
            $this->fail('Expected the count to be stale.');
        } catch (ValidationException $exception) {
            $this->assertSame([__('inventory.errors.count_stale')], $exception->errors()['count']);
        }

        $this->assertSame(8, $this->balance());
        $this->assertSame(3, InventoryMovement::count());

        $component = Livewire::actingAs($this->owner)
            ->test(ProductInventory::class, ['store' => $this->store, 'productDefinition' => $this->cable])
            ->call('startCount');

        $this->recordCount(9, (int) InventoryMovement::query()->max('id'), 'Concurrent count', 'concurrent');

        $component
            ->set('countedQuantity', '10')
            ->set('countReason', 'Shelf count')
            ->call('recordCount')
            ->assertHasErrors('count')
            ->assertSet('countInProgress', true)
            ->call('startCount')
            ->assertSet('countedQuantity', '')
            ->set('countedQuantity', '10')
            ->call('recordCount')
            ->assertHasNoErrors()
            ->assertViewHas('quantity', 10);
    }

    #[Test]
    public function history_shows_a_counted_total_only_for_physical_counts_and_keeps_legacy_corrections_as_balance_after(): void
    {
        $legacy = InventoryMovement::query()->create([
            'store_id' => $this->store->id,
            'product_definition_id' => $this->cable->id,
            'type' => MovementType::ADJUSTMENT_DECREASE,
            'reason' => AdjustmentReason::COUNT_CORRECTION,
            'quantity_change' => -2,
            'quantity_after' => 6,
            'note' => 'Legacy relative adjustment',
            'user_id' => $this->owner->id,
            'idempotency_key' => 'legacy',
            'request_fingerprint' => str_repeat('0', 64),
            'occurred_at' => now(),
        ]);
        InventoryBalance::query()->where('store_id', $this->store->id)->update(['quantity' => 6]);

        $count = $this->recordCount(9, $legacy->id, 'Weekly count');

        $html = Livewire::actingAs($this->owner)
            ->test(ProductInventory::class, ['store' => $this->store, 'productDefinition' => $this->cable])
            ->html();

        $legacyRow = $this->historyRow($html, $legacy);
        $this->assertStringContainsString('تصحيح جرد', $legacyRow);
        $this->assertStringContainsString('الرصيد بعدها', $legacyRow);
        $this->assertStringNotContainsString('العدد الفعلي', $legacyRow);
        $this->assertStringNotContainsString('الرصيد السابق', $legacyRow);

        $countRow = $this->historyRow($html, $count);
        $this->assertStringContainsString('جرد فعلي', $countRow);
        $this->assertMatchesRegularExpression('/الرصيد السابق: <span[^>]*>6</', $countRow);
        $this->assertMatchesRegularExpression('/العدد الفعلي: <span[^>]*>9</', $countRow);
        $this->assertStringNotContainsString('الرصيد بعدها', $countRow);

        $legacy->refresh();
        $this->assertSame(AdjustmentReason::COUNT_CORRECTION, $legacy->reason);
        $this->assertSame([-2, 6], [$legacy->quantity_change, $legacy->quantity_after]);
    }

    #[Test]
    public function counting_requires_the_branch_adjustment_permission(): void
    {
        $employee = User::factory()->create()->assignRole('employee');
        $this->store->employees()->attach($employee);

        $this->assertThrows(
            fn () => app(RecordStockCount::class)($employee, $this->store, $this->cable, 10, $this->opening->id, 'Count', 'employee'),
            AuthorizationException::class,
        );

        $this->revokeFromOwners('adjust_inventory');

        $this->assertThrows(fn () => $this->recordCount(10, $this->opening->id, 'Count', 'owner'), AuthorizationException::class);

        Livewire::actingAs($this->owner)
            ->test(ProductInventory::class, ['store' => $this->store, 'productDefinition' => $this->cable])
            ->assertViewHas('canAdjust', false)
            ->call('startCount')
            ->assertForbidden();

        $this->assertSame(8, $this->balance());
        $this->assertSame(1, InventoryMovement::count());
    }

    private function recordCount(int $counted, ?int $countStartedAfter, string $reason, string $key = 'count'): ?InventoryMovement
    {
        return app(RecordStockCount::class)($this->owner, $this->store, $this->cable, $counted, $countStartedAfter, $reason, $key);
    }

    private function historyRow(string $html, InventoryMovement $movement): string
    {
        $this->assertSame(1, preg_match('/wire:key="movement-'.$movement->id.'".*?<\/li>/s', $html, $match));

        return $match[0];
    }

    private function balance(): int
    {
        return (int) InventoryBalance::query()->where('store_id', $this->store->id)->where('product_definition_id', $this->cable->id)->value('quantity');
    }
}
