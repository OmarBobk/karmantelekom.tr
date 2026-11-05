<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Inventory\CorrectUnitIdentifier;
use App\Actions\Inventory\FindUnitsByIdentifier;
use App\Enums\TrackingMode;
use App\Enums\UnitCondition;
use App\Livewire\Backend\Inventory\DeviceLookup;
use App\Livewire\Backend\Inventory\InventoryStores;
use App\Livewire\Backend\Inventory\StoreInventory;
use App\Models\InventoryUnit;
use App\Models\ProductDefinition;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\InteractsWithDeviceLifecycle;
use Tests\Support\InteractsWithOperationalInventory;
use Tests\TestCase;

class DeviceLookupTest extends TestCase
{
    use InteractsWithDeviceLifecycle;
    use InteractsWithOperationalInventory;
    use RefreshDatabase;

    private User $owner;

    private Store $storeOne;

    private Store $storeTwo;

    private ProductDefinition $usedPhone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOperationalRoles();
        $this->useEnglishLocale();
        $this->owner = $this->createOwner();
        $this->storeOne = Store::factory()->create(['code' => 'KT01']);
        $this->storeTwo = Store::factory()->create(['code' => 'KT02']);
        $this->usedPhone = ProductDefinition::factory()->create(['name' => 'iPhone 13', 'tracking_mode' => TrackingMode::USED_DEVICE]);
    }

    #[Test]
    public function an_exact_imei_in_either_slot_finds_one_current_device(): void
    {
        $primary = self::imei('35693803564380');
        $secondary = self::imei('35693803564381');
        $unit = $this->registerDevice($this->owner, $this->storeTwo, $this->usedPhone, $primary, secondaryImei: $secondary);

        foreach ([$primary, $secondary] as $position => $code) {
            $result = $this->find($code);

            $this->assertSame([$unit->id], array_column($result['current'], 'unit_id'));
            $this->assertSame($position + 1, $result['current'][0]['position']);
            $this->assertSame([], $result['historical']);
        }

        $this->assertSame([], $this->find(substr($primary, 0, 14))['current']);
        $this->assertSame([], $this->find(substr($primary, 1))['current']);
    }

    #[Test]
    public function pasted_imeis_with_grouping_or_arabic_digits_match_exactly(): void
    {
        $imei = self::imei('35693803564380');
        $unit = $this->registerDevice($this->owner, $this->storeOne, $this->usedPhone, $imei);
        $arabic = strtr($imei, ['0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤', '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩']);

        foreach ([" {$imei} ", implode(' ', str_split($imei, 5)), implode('-', str_split($imei, 5)), $arabic] as $pasted) {
            $this->assertSame([$unit->id], array_column($this->find($pasted)['current'], 'unit_id'), "Input: {$pasted}");
        }
    }

    #[Test]
    public function serials_match_exactly_including_leading_zeros_and_case(): void
    {
        $zeros = $this->registerDevice($this->owner, $this->storeOne, $this->usedPhone, null, serial: '000123');
        $plain = $this->registerDevice($this->owner, $this->storeOne, $this->usedPhone, null, serial: '123');
        $this->registerDevice($this->owner, $this->storeOne, $this->usedPhone, null, serial: 'AB12');

        $this->assertSame([$zeros->id], array_column($this->find('000123')['current'], 'unit_id'));
        $this->assertSame([$plain->id], array_column($this->find('123')['current'], 'unit_id'));
        $this->assertSame([], $this->find('ab12')['current']);
        $this->assertSame([], $this->find('0123')['current']);
    }

    #[Test]
    public function a_serial_that_looks_like_an_imei_is_still_offered_and_both_need_a_choice(): void
    {
        $value = self::imei('35693803564380');
        $byImei = $this->registerDevice($this->owner, $this->storeOne, $this->usedPhone, $value);
        $otherProduct = ProductDefinition::factory()->create(['tracking_mode' => TrackingMode::SERIALIZED]);
        $bySerial = $this->registerDevice($this->owner, $this->storeOne, $otherProduct, null, serial: $value, condition: UnitCondition::NEW);

        $this->assertEqualsCanonicalizing([$byImei->id, $bySerial->id], array_column($this->find($value)['current'], 'unit_id'));

        Livewire::actingAs($this->owner)
            ->test(DeviceLookup::class)
            ->call('lookup', $value, 1)
            ->assertReturned(fn (array $result) => $result['url'] === null && $result['matches'] === 2)
            ->assertSee('2 devices match. Choose the right one.');
    }

    #[Test]
    public function a_corrected_away_value_is_only_a_historical_match_that_needs_explicit_selection(): void
    {
        $typo = self::imei('35693803564380');
        $correct = self::imei('35693803564381');
        $unit = $this->registerDevice($this->owner, $this->storeOne, $this->usedPhone, $typo);
        $identifier = $unit->identifiers()->sole();
        app(CorrectUnitIdentifier::class)($this->owner, $this->storeOne, $unit->id, $identifier->id, $typo, $correct, 'Typo', 'fix-1');

        $result = $this->find($typo);
        $this->assertSame([], $result['current']);
        $this->assertSame([$unit->id], array_column($result['historical'], 'unit_id'));
        $this->assertSame($correct, $result['historical'][0]['current_value']);

        Livewire::actingAs($this->owner)
            ->test(DeviceLookup::class)
            ->call('lookup', $typo, 1)
            ->assertReturned(fn (array $result) => $result['url'] === null)
            ->assertSee(['Previously recorded values', 'Old value', $correct]);

        $reused = $this->registerDevice($this->owner, $this->storeTwo, $this->usedPhone, $typo);
        $result = $this->find($typo);
        $this->assertSame([$reused->id], array_column($result['current'], 'unit_id'));
        $this->assertSame([$unit->id], array_column($result['historical'], 'unit_id'));
    }

    #[Test]
    public function a_single_current_match_returns_its_page_and_a_store_scoped_lookup_ignores_other_stores(): void
    {
        $imei = self::imei('35693803564380');
        $unit = $this->registerDevice($this->owner, $this->storeTwo, $this->usedPhone, $imei);

        Livewire::actingAs($this->owner)
            ->test(DeviceLookup::class)
            ->call('lookup', $imei, 1)
            ->assertReturned(fn (array $result) => $result['url'] === route('subdomain.inventory.unit', [$this->storeTwo, $unit]));

        Livewire::actingAs($this->owner)
            ->test(DeviceLookup::class, ['store' => $this->storeOne])
            ->call('lookup', $imei, 1)
            ->assertReturned(fn (array $result) => $result['url'] === null && $result['matches'] === 0)
            ->assertSee('No device with this exact IMEI or serial number.')
            ->assertDontSee('KT02');
    }

    #[Test]
    public function an_older_response_never_replaces_a_newer_lookup(): void
    {
        $this->registerDevice($this->owner, $this->storeOne, $this->usedPhone, self::imei('35693803564380'));
        $second = $this->registerDevice($this->owner, $this->storeOne, $this->usedPhone, self::imei('35693803564381'));

        Livewire::actingAs($this->owner)
            ->test(DeviceLookup::class)
            ->call('lookup', self::imei('35693803564381'), 2)
            ->assertReturned(fn (array $result) => str_ends_with((string) $result['url'], '/'.$second->id))
            ->call('lookup', self::imei('35693803564380'), 1)
            ->assertReturned(fn (array $result) => $result['url'] === null && $result['matches'] === 0)
            ->assertSet('code', self::imei('35693803564381'))
            ->assertSet('sequence', 2);
    }

    #[Test]
    public function lookup_needs_inventory_access_and_its_scope_cannot_be_tampered(): void
    {
        $this->actingAs($this->owner)->get(route('subdomain.inventory'))->assertOk()->assertSeeLivewire(InventoryStores::class)->assertSeeLivewire(DeviceLookup::class);
        $this->actingAs($this->owner)->get(route('subdomain.inventory.store', $this->storeOne))->assertOk()->assertSeeLivewire(StoreInventory::class)->assertSee('inputmode="text"', false);

        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::actingAs($this->owner)->test(DeviceLookup::class, ['store' => $this->storeOne])->set('storeId', $this->storeTwo->id);
    }

    #[Test]
    public function revoked_inventory_access_returns_nothing(): void
    {
        $imei = self::imei('35693803564380');
        $this->registerDevice($this->owner, $this->storeOne, $this->usedPhone, $imei);
        $component = Livewire::actingAs($this->owner)->test(DeviceLookup::class);

        $this->revokeFromOwners('view_inventory');

        $component->call('lookup', $imei, 1)->assertForbidden();
        $this->assertSame(1, InventoryUnit::count());
    }

    /**
     * @return array{current: list<array<string, mixed>>, historical: list<array<string, mixed>>}
     */
    private function find(string $code, ?Store $store = null): array
    {
        return app(FindUnitsByIdentifier::class)($this->owner, $code, $store);
    }
}
