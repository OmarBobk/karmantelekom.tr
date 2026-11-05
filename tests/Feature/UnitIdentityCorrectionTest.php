<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Inventory\CorrectUnitIdentifier;
use App\Actions\Inventory\RemoveUnit;
use App\Enums\AdjustmentReason;
use App\Enums\IdentifierType;
use App\Enums\TrackingMode;
use App\Exceptions\Inventory\StaleUnitStateException;
use App\Livewire\Backend\Inventory\UnitDetail;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\InventoryUnitIdentifierCorrection;
use App\Models\ProductDefinition;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\InteractsWithDeviceLifecycle;
use Tests\Support\InteractsWithOperationalInventory;
use Tests\TestCase;

class UnitIdentityCorrectionTest extends TestCase
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
        $this->useEnglishLocale();
        $this->owner = $this->createOwner();
        $this->store = Store::factory()->create(['code' => 'KT01']);
        $this->usedPhone = ProductDefinition::factory()->create(['name' => 'iPhone 13', 'tracking_mode' => TrackingMode::USED_DEVICE]);
    }

    #[Test]
    public function an_imei_typo_is_corrected_in_place_and_the_old_value_is_released(): void
    {
        $typo = self::imei('35693803564380');
        $correct = self::imei('35693803564381');
        $unit = $this->registerDevice($this->owner, $this->store, $this->usedPhone, $typo);
        $identifier = $unit->identifiers()->sole();

        $movement = $this->correct($unit, $identifier->id, $typo, $correct, 'Digit misread at intake');

        $identifier->refresh();
        $this->assertSame($correct, $identifier->value);
        $this->assertSame(1, $unit->identifiers()->count());
        $this->assertSame(0, $movement->quantity_change);
        $this->assertSame('identifier_corrected', $movement->type->value);
        $this->assertSame(0, $unit->fresh()->lifecycle_revision);

        $correction = InventoryUnitIdentifierCorrection::query()->sole();
        $this->assertSame([$typo, $correct, 'Digit misread at intake', $movement->id], [$correction->old_value, $correction->new_value, $correction->reason, $correction->inventory_movement_id]);

        $this->expectException(LogicException::class);
        $correction->update(['new_value' => $typo]);
    }

    #[Test]
    public function the_released_value_can_be_registered_to_another_device(): void
    {
        $typo = self::imei('35693803564380');
        $unit = $this->registerDevice($this->owner, $this->store, $this->usedPhone, $typo);
        $this->correct($unit, $unit->identifiers()->sole()->id, $typo, self::imei('35693803564381'), 'Typo');

        $other = $this->registerDevice($this->owner, $this->store, $this->usedPhone, $typo);

        $this->assertSame($typo, $other->identifiers()->sole()->value);
    }

    #[Test]
    public function a_value_held_by_any_other_unit_is_rejected(): void
    {
        $removedImei = self::imei('35693803564381');
        $secondaryImei = self::imei('35693803564382');
        $removed = $this->registerDevice($this->owner, $this->store, $this->usedPhone, $removedImei);
        app(RemoveUnit::class)($this->owner, $this->store, $removed->id, AdjustmentReason::DAMAGED, 'Broken', 'remove-1');
        $this->registerDevice($this->owner, Store::factory()->create(), $this->usedPhone, self::imei('35693803564383'), secondaryImei: $secondaryImei);
        $this->registerDevice($this->owner, $this->store, $this->usedPhone, null, serial: 'SN-0042');

        $unit = $this->registerDevice($this->owner, $this->store, $this->usedPhone, self::imei('35693803564380'), serial: 'SN-0041');
        $imei = $unit->identifiers()->where('type', IdentifierType::IMEI)->sole();
        $serial = $unit->identifiers()->where('type', IdentifierType::SERIAL)->sole();

        $this->assertRejected(fn () => $this->correct($unit, $imei->id, $imei->value, $removedImei, 'Typo'), 'This IMEI is already registered to another device.');
        $this->assertRejected(fn () => $this->correct($unit, $imei->id, $imei->value, $secondaryImei, 'Typo'), 'This IMEI is already registered to another device.');
        $this->assertRejected(fn () => $this->correct($unit, $serial->id, $serial->value, 'SN-0042', 'Typo'), 'This serial number is already registered for this product.');
        $this->assertRejected(fn () => $this->correct($unit, $imei->id, $imei->value, '356938035643801', 'Typo'), 'An IMEI must be 15 digits with a valid check digit.');
        $this->assertRejected(fn () => $this->correct($unit, $imei->id, $imei->value, $imei->value, 'Typo'), 'The corrected value is the same as the current value.');
        $this->assertRejected(fn () => $this->correct($unit, $imei->id, $imei->value, self::imei('35693803564389'), '  '), 'The Reason for correction field is required.', 'correction_reason');

        $this->assertSame(0, InventoryUnitIdentifierCorrection::count());
        $this->assertSame(0, InventoryMovement::query()->where('type', 'identifier_corrected')->count());
    }

    #[Test]
    public function serials_keep_leading_zeros_and_letters(): void
    {
        $unit = $this->registerDevice($this->owner, $this->store, $this->usedPhone, null, serial: 'F2LX0001');
        $serial = $unit->identifiers()->sole();

        $this->correct($unit, $serial->id, 'F2LX0001', '00F2LX0001', 'Leading zeros dropped at intake');

        $this->assertSame('00F2LX0001', $serial->fresh()->value);
    }

    #[Test]
    public function a_correction_based_on_an_outdated_value_is_rejected(): void
    {
        $unit = $this->registerDevice($this->owner, $this->store, $this->usedPhone, self::imei('35693803564380'));
        $identifier = $unit->identifiers()->sole();
        $this->correct($unit, $identifier->id, $identifier->value, self::imei('35693803564381'), 'First fix');

        $this->expectException(StaleUnitStateException::class);
        $this->correct($unit, $identifier->id, self::imei('35693803564380'), self::imei('35693803564382'), 'Second fix from an old form');
    }

    #[Test]
    public function the_unit_page_corrects_an_identifier_after_confirmation_and_keeps_input_on_errors(): void
    {
        $typo = self::imei('35693803564380');
        $unit = $this->registerDevice($this->owner, $this->store, $this->usedPhone, $typo);
        $identifier = $unit->identifiers()->sole();

        Livewire::actingAs($this->owner)
            ->test(UnitDetail::class, ['store' => $this->store, 'unit' => $unit])
            ->assertSee(['Correct identifier', 'inputmode', 'Current value', 'Corrected value'])
            ->set('correctingIdentifierId', (string) $identifier->id)
            ->set('correctionExpectedValue', $typo)
            ->set('newIdentifierValue', self::imei('35693803564381'))
            ->set('correctionReason', 'Misread')
            ->call('correctIdentifier')
            ->assertHasErrors('correctionConfirmed')
            ->assertSet('newIdentifierValue', self::imei('35693803564381'))
            ->set('correctionConfirmed', true)
            ->call('correctIdentifier')
            ->assertHasNoErrors()
            ->assertDispatched('close-dialog', id: 'correct-identifier')
            ->assertSet('newIdentifierValue', '')
            ->assertSee(['Identifier corrected', $typo, self::imei('35693803564381'), 'Misread']);
    }

    #[Test]
    public function identity_correction_needs_its_permission_and_cannot_reach_another_units_identifier(): void
    {
        $unit = $this->registerDevice($this->owner, $this->store, $this->usedPhone, self::imei('35693803564380'));
        $other = $this->registerDevice($this->owner, $this->store, $this->usedPhone, self::imei('35693803564381'));
        $otherIdentifier = $other->identifiers()->sole();

        try {
            $this->correct($unit, $otherIdentifier->id, $otherIdentifier->value, self::imei('35693803564382'), 'Tampered');
            $this->fail('Another unit\'s identifier must not be found.');
        } catch (ModelNotFoundException) {
            $this->assertSame(self::imei('35693803564381'), $otherIdentifier->fresh()->value);
        }

        $this->revokeFromOwners('correct_unit_identity');

        Livewire::actingAs($this->owner)
            ->test(UnitDetail::class, ['store' => $this->store, 'unit' => $unit])
            ->assertDontSee('Correct identifier')
            ->set('correctingIdentifierId', (string) $unit->identifiers()->sole()->id)
            ->set('correctionExpectedValue', self::imei('35693803564380'))
            ->set('newIdentifierValue', self::imei('35693803564382'))
            ->set('correctionReason', 'Typo')
            ->set('correctionConfirmed', true)
            ->call('correctIdentifier')
            ->assertForbidden();

        $this->assertSame(0, InventoryUnitIdentifierCorrection::count());
    }

    private function correct(InventoryUnit $unit, int $identifierId, string $expected, ?string $newValue, ?string $reason): InventoryMovement
    {
        return app(CorrectUnitIdentifier::class)($this->owner, $unit->store, $unit->id, $identifierId, $expected, $newValue, $reason, uniqid('fix-', true));
    }

    private function assertRejected(callable $action, string $message, string $key = 'new_value'): void
    {
        try {
            $action();
            $this->fail("Expected: {$message}");
        } catch (ValidationException $exception) {
            $this->assertSame([$message], $exception->errors()[$key] ?? null);
        }
    }
}
