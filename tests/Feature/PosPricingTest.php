<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Catalog\SaveProductDefinition;
use App\Actions\Pos\SetUnitSellingPrice;
use App\Enums\TrackingMode;
use App\Enums\UnitCondition;
use App\Livewire\Backend\Inventory\UnitDetail;
use App\Livewire\Backend\OperationalCatalog\DefinitionsIndex;
use App\Models\ProductDefinition;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\InteractsWithPos;
use Tests\TestCase;

class PosPricingTest extends TestCase
{
    use InteractsWithPos;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCounter();
        $this->useEnglishLocale();
    }

    #[Test]
    public function the_owner_prices_ordinary_definitions_from_the_catalog_form(): void
    {
        Livewire::actingAs($this->owner)->test(DefinitionsIndex::class)
            ->call('openEditForm', $this->cable->id)
            ->assertSet('sellingPrice', '49.90')
            ->set('sellingPrice', '٥٤٫٥٠')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('54.50', $this->cable->fresh()->selling_price);
        $activity = Activity::query()->where('log_name', 'selling_price')->sole();
        $this->assertSame('definition_price_set', $activity->event);
        $this->assertSame(['49.90', '54.50'], [$activity->properties['old'], $activity->properties['new']]);

        Livewire::actingAs($this->owner)->test(DefinitionsIndex::class)
            ->call('openEditForm', $this->cable->id)
            ->set('sellingPrice', '0')
            ->call('save')
            ->assertHasErrors('selling_price');
        $this->assertSame('54.50', $this->cable->fresh()->selling_price);
    }

    #[Test]
    public function used_device_definitions_never_carry_a_shared_price(): void
    {
        $this->expectValidationError(
            fn () => app(SaveProductDefinition::class)($this->owner, $this->usedPhone, ['name' => 'iPhone 13', 'tracking_mode' => 'used_device', 'selling_price' => '9000']),
            'selling_price',
        );

        $definition = app(SaveProductDefinition::class)($this->owner, null, ['name' => 'Pixel 8', 'tracking_mode' => 'used_device', 'selling_price' => '']);
        $this->assertNull($definition->selling_price);
        $this->assertSame(TrackingMode::USED_DEVICE, $definition->tracking_mode);
    }

    #[Test]
    public function a_catalog_manager_without_the_price_permission_cannot_set_prices(): void
    {
        $this->revokeFromOwners('set_selling_prices');

        $this->expectException(AuthorizationException::class);
        app(SaveProductDefinition::class)($this->owner->fresh(), $this->cable, ['name' => 'USB-C cable', 'tracking_mode' => 'quantity', 'selling_price' => '10']);
    }

    #[Test]
    public function saving_a_definition_without_a_price_key_keeps_its_price(): void
    {
        $this->revokeFromOwners('set_selling_prices');

        Livewire::actingAs($this->owner->fresh())->test(DefinitionsIndex::class)
            ->call('openEditForm', $this->cable->id)
            ->set('name', 'USB-C cable 1m')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('49.90', $this->cable->fresh()->selling_price);
        $this->assertSame('USB-C cable 1m', $this->cable->fresh()->name);
    }

    #[Test]
    public function the_owner_prices_a_used_device_on_its_unit_page_without_advancing_its_revision(): void
    {
        $revision = $this->phone->lifecycle_revision;

        Livewire::actingAs($this->owner)->test(UnitDetail::class, ['store' => $this->store, 'unit' => $this->phone])
            ->set('unitSellingPrice', '11999.99')
            ->call('saveSellingPrice')
            ->assertHasNoErrors();

        $this->phone->refresh();
        $this->assertSame('11999.99', $this->phone->selling_price);
        $this->assertSame($revision, $this->phone->lifecycle_revision);
        $this->assertSame('unit_price_set', Activity::query()->where('log_name', 'selling_price')->sole()->event);
    }

    #[Test]
    public function unit_prices_are_limited_to_used_devices_that_are_not_sold(): void
    {
        $cashier = $this->createCashier();

        $this->expectAuthorizationFailure(fn () => app(SetUnitSellingPrice::class)($cashier, $this->store, $this->phone->id, '100'));

        $this->finalize($cashier, $this->review($cashier, $this->phoneLine()), '12500');

        $this->expectValidationError(fn () => app(SetUnitSellingPrice::class)($this->owner, $this->store, $this->phone->id, '100'), 'selling_price');
        $this->assertSame('12500.00', $this->phone->fresh()->selling_price);

        $headset = ProductDefinition::factory()->priced('1500')->create(['tracking_mode' => TrackingMode::SERIALIZED]);
        $unit = $this->registerDevice($this->owner, $this->store, $headset, null, 'HS-9', condition: UnitCondition::NEW);
        $this->expectValidationError(fn () => app(SetUnitSellingPrice::class)($this->owner, $this->store, $unit->id, '100'), 'selling_price');
    }

    private function expectValidationError(callable $callback, string $field): void
    {
        try {
            $callback();
            $this->fail("Expected a validation error on {$field}.");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    private function expectAuthorizationFailure(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected an authorization failure.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }
    }
}
