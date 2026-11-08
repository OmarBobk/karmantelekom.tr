<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Inventory\RecordOpeningBalance;
use App\Actions\Inventory\RegisterOpeningUnit;
use App\Actions\Purchasing\ReceivePurchase;
use App\Actions\Purchasing\ReversePurchase;
use App\Enums\MovementType;
use App\Enums\TrackingMode;
use App\Enums\UnitCondition;
use App\Exceptions\Inventory\IdempotencyConflictException;
use App\Exceptions\Purchasing\PurchaseAlreadyReceivedException;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\InventoryUnitIdentifier;
use App\Models\OperationalSupplier;
use App\Models\ProductDefinition;
use App\Models\Purchase;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptLine;
use App\Models\PurchaseReceiptUnit;
use App\Models\Store;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Support\InteractsWithOperationalInventory;
use Tests\Support\InteractsWithPurchasing;
use Tests\TestCase;

class PurchaseReceivingTest extends TestCase
{
    use InteractsWithOperationalInventory;
    use InteractsWithPurchasing;
    use RefreshDatabase;

    private User $owner;

    private Store $store;

    private OperationalSupplier $supplier;

    private ProductDefinition $cable;

    private ProductDefinition $usedPhone;

    private Purchase $purchase;

    private string $imei;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOperationalRoles();
        $this->owner = $this->createOwner();
        $this->store = Store::factory()->create(['code' => 'KT01']);
        Store::factory()->create(['code' => 'KT02']);
        $this->supplier = OperationalSupplier::factory()->create();
        $this->cable = ProductDefinition::factory()->create(['tracking_mode' => TrackingMode::QUANTITY]);
        $this->usedPhone = ProductDefinition::factory()->create(['tracking_mode' => TrackingMode::USED_DEVICE]);
        $this->imei = self::imei('35693803564380');

        $this->purchase = $this->postedPurchase($this->owner, $this->store, $this->supplier, [
            [$this->cable, 10, '12.50'],
            [$this->usedPhone, 1, '8000.00'],
        ], ['additional_charges' => '50.00']);
    }

    /**
     * @return array<int, list<array<string, ?string>>>
     */
    private function phoneUnits(?string $imei = null, string $condition = 'b'): array
    {
        $phoneLine = $this->purchase->lines()->where('product_definition_id', $this->usedPhone->id)->sole();

        return [$phoneLine->id => [['condition' => $condition, 'imei' => $imei ?? $this->imei, 'secondary_imei' => null, 'serial' => null]]];
    }

    #[Test]
    public function a_full_receipt_adds_stock_and_links_every_movement_and_device_to_the_receipt(): void
    {
        $receipt = app(ReceivePurchase::class)($this->owner, $this->purchase, $this->phoneUnits(), 'Delivered complete', 'receipt-1');

        $this->assertTrue($receipt->wasRecentlyCreated);
        $this->assertNotNull($this->purchase->refresh()->received_at);
        $this->assertSame(10, (int) InventoryBalance::query()->where('store_id', $this->store->id)->where('product_definition_id', $this->cable->id)->value('quantity'));

        $unit = InventoryUnit::query()->sole();
        $this->assertSame($this->store->id, $unit->store_id);
        $this->assertSame(UnitCondition::B, $unit->condition);
        $this->assertSame($this->imei, $unit->primaryIdentifierValue());

        $movements = InventoryMovement::query()->get();
        $this->assertCount(2, $movements);

        foreach ($movements as $movement) {
            $this->assertSame(MovementType::PURCHASE_RECEIPT, $movement->type);
            $this->assertSame(PurchaseReceiptLine::MORPH_ALIAS, $movement->reference_type);
            $this->assertInstanceOf(PurchaseReceiptLine::class, $movement->reference);
            $this->assertSame($receipt->id, $movement->reference->purchase_receipt_id);
        }

        $acquisition = PurchaseReceiptUnit::query()->sole();
        $this->assertSame($unit->id, $acquisition->inventory_unit_id);
        $this->assertSame('8000.00', $acquisition->unit_cost);
        $this->assertSame('TRY', $acquisition->currency->value);
    }

    #[Test]
    public function a_retry_with_the_same_key_returns_the_original_receipt_once(): void
    {
        $first = app(ReceivePurchase::class)($this->owner, $this->purchase, $this->phoneUnits(), null, 'receipt-1');
        $retry = app(ReceivePurchase::class)($this->owner, $this->purchase, $this->phoneUnits(), null, 'receipt-1');

        $this->assertSame($first->id, $retry->id);
        $this->assertFalse($retry->wasRecentlyCreated);
        $this->assertSame(1, PurchaseReceipt::count());
        $this->assertSame(2, InventoryMovement::count());
        $this->assertSame(10, (int) InventoryBalance::query()->value('quantity'));
    }

    #[Test]
    public function the_same_key_with_different_details_is_a_conflict(): void
    {
        app(ReceivePurchase::class)($this->owner, $this->purchase, $this->phoneUnits(), null, 'receipt-1');

        $this->expectException(IdempotencyConflictException::class);
        app(ReceivePurchase::class)($this->owner, $this->purchase, $this->phoneUnits(condition: 'a'), null, 'receipt-1');
    }

    #[Test]
    public function a_second_receipt_with_a_new_key_is_reported_as_already_received(): void
    {
        app(ReceivePurchase::class)($this->owner, $this->purchase, $this->phoneUnits(), null, 'receipt-1');

        try {
            app(ReceivePurchase::class)($this->owner, $this->purchase, $this->phoneUnits(self::imei('35693803564381')), null, 'receipt-2');
            $this->fail('A second receipt was accepted.');
        } catch (PurchaseAlreadyReceivedException) {
        }

        $this->assertSame(1, PurchaseReceipt::count());
        $this->assertSame(1, InventoryUnit::count());
    }

    #[Test]
    public function a_receipt_that_fails_after_stock_writes_rolls_back_completely(): void
    {
        Event::listen('eloquent.creating: '.PurchaseReceiptUnit::class, function (): void {
            throw new RuntimeException('Simulated failure after the cable stock write.');
        });

        try {
            app(ReceivePurchase::class)($this->owner, $this->purchase, $this->phoneUnits(), null, 'receipt-1');
            $this->fail('The simulated failure did not happen.');
        } catch (RuntimeException) {
        }

        $this->assertSame(0, PurchaseReceipt::count());
        $this->assertSame(0, PurchaseReceiptLine::count());
        $this->assertSame(0, InventoryMovement::count());
        $this->assertSame(0, InventoryUnit::count());
        $this->assertSame(0, InventoryUnitIdentifier::count());
        $this->assertSame(0, (int) InventoryBalance::query()->sum('quantity'));
        $this->assertNull($this->purchase->refresh()->received_at);

        Event::forget('eloquent.creating: '.PurchaseReceiptUnit::class);
        app(ReceivePurchase::class)($this->owner, $this->purchase, $this->phoneUnits(), null, 'receipt-1');
        $this->assertSame(10, (int) InventoryBalance::query()->sum('quantity'));
    }

    #[Test]
    public function a_device_identity_already_in_stock_is_rejected_before_any_write(): void
    {
        app(RegisterOpeningUnit::class)($this->owner, $this->store, $this->usedPhone, UnitCondition::A, $this->imei, null, null, null, 'existing');

        try {
            app(ReceivePurchase::class)($this->owner, $this->purchase, $this->phoneUnits(), null, 'receipt-1');
            $this->fail('A duplicate IMEI was received.');
        } catch (ValidationException $exception) {
            $phoneLineId = array_key_first($this->phoneUnits());
            $this->assertArrayHasKey("units.{$phoneLineId}.0.imei", $exception->errors());
        }

        $this->assertSame(0, PurchaseReceipt::count());
        $this->assertSame(1, InventoryMovement::count());
    }

    #[Test]
    public function identifiers_repeated_within_one_receipt_are_rejected(): void
    {
        $purchase = $this->postedPurchase($this->owner, $this->store, $this->supplier, [[$this->usedPhone, 2, '100.00']], ['supplier_invoice_number' => 'TWO-PHONES']);
        $line = $purchase->lines()->sole();

        $this->expectException(ValidationException::class);
        app(ReceivePurchase::class)($this->owner, $purchase, [$line->id => [
            ['condition' => 'a', 'imei' => $this->imei],
            ['condition' => 'b', 'imei' => $this->imei],
        ]], null, 'receipt-two');
    }

    #[Test]
    public function every_invoiced_device_needs_its_identity_and_an_allowed_condition(): void
    {
        $phoneLineId = array_key_first($this->phoneUnits());

        try {
            app(ReceivePurchase::class)($this->owner, $this->purchase, [$phoneLineId => [['condition' => 'new', 'imei' => '123']]], null, 'receipt-1');
            $this->fail('An invalid unit was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey("units.{$phoneLineId}.0.condition", $exception->errors());
            $this->assertArrayHasKey("units.{$phoneLineId}.0.imei", $exception->errors());
        }

        try {
            app(ReceivePurchase::class)($this->owner, $this->purchase, [], null, 'receipt-1');
            $this->fail('A receipt without the device was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey("lines.{$phoneLineId}", $exception->errors());
        }

        $this->assertSame(0, InventoryMovement::count());
    }

    #[Test]
    public function received_stock_is_history_so_opening_balances_are_no_longer_allowed(): void
    {
        app(ReceivePurchase::class)($this->owner, $this->purchase, $this->phoneUnits(), null, 'receipt-1');

        $this->expectException(ValidationException::class);
        app(RecordOpeningBalance::class)($this->owner, $this->store, $this->cable, 5, null, 'opening');
    }

    #[Test]
    public function received_invoices_cannot_be_reversed(): void
    {
        app(ReceivePurchase::class)($this->owner, $this->purchase, $this->phoneUnits(), null, 'receipt-1');

        $this->expectException(ValidationException::class);
        app(ReversePurchase::class)($this->owner, $this->purchase, 'Too late');
    }

    #[Test]
    public function drafts_and_reversed_invoices_cannot_be_received(): void
    {
        app(ReversePurchase::class)($this->owner, $this->purchase, 'Wrong supplier');

        $this->expectException(ValidationException::class);
        app(ReceivePurchase::class)($this->owner, $this->purchase, $this->phoneUnits(), null, 'receipt-1');
    }

    #[Test]
    public function receiving_requires_the_receive_permission(): void
    {
        $this->revokeFromOwners('receive_purchases');

        $this->expectException(AuthorizationException::class);
        app(ReceivePurchase::class)($this->owner->fresh(), $this->purchase, $this->phoneUnits(), null, 'receipt-1');
    }
}
