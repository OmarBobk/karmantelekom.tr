<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Catalog\GenerateInternalBarcode;
use App\Actions\Catalog\InternalBarcodeAllocator;
use App\Actions\Catalog\SaveProductDefinition;
use App\Actions\Inventory\RecordOpeningBalance;
use App\Enums\TrackingMode;
use App\Enums\UnitCondition;
use App\Exceptions\Inventory\IdempotencyConflictException;
use App\Livewire\Backend\OperationalCatalog\DefinitionsIndex;
use App\Livewire\Pos\PosCounter;
use App\Models\CatalogBarcodeSequence;
use App\Models\InventoryUnitIdentifier;
use App\Models\ProductDefinition;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\OperationalStoreSetupSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\InteractsWithDeviceLifecycle;
use Tests\Support\InteractsWithOperationalInventory;
use Tests\TestCase;

class InternalBarcodeTest extends TestCase
{
    use InteractsWithDeviceLifecycle;
    use InteractsWithOperationalInventory;
    use RefreshDatabase;

    private User $owner;

    private SaveProductDefinition $save;

    private GenerateInternalBarcode $generate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOperationalRoles();
        $this->owner = $this->createOwner();
        $this->save = app(SaveProductDefinition::class);
        $this->generate = app(GenerateInternalBarcode::class);
        $this->useEnglishLocale();

        CatalogBarcodeSequence::query()->insertOrIgnore([
            'id' => CatalogBarcodeSequence::SINGLETON_ID,
            'last_number' => InternalBarcodeAllocator::MIN - 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    #[Test]
    public function create_with_blank_barcode_allocates_the_first_internal_code(): void
    {
        $definition = ($this->save)($this->owner, null, [
            'name' => 'USB-C Cable',
            'tracking_mode' => 'quantity',
        ]);

        $this->assertSame('200001', $definition->barcode);
        $this->assertSame(200001, CatalogBarcodeSequence::query()->value('last_number'));
    }

    #[Test]
    public function create_preserves_manufacturer_barcodes_and_leading_zeros(): void
    {
        $definition = ($this->save)($this->owner, null, [
            'name' => 'Anker Cable',
            'tracking_mode' => 'quantity',
            'barcode' => '0012345678905',
        ]);

        $this->assertSame('0012345678905', $definition->fresh()->barcode);
        $this->assertSame(200000, CatalogBarcodeSequence::query()->value('last_number'));
    }

    #[Test]
    public function allocation_skips_codes_used_as_sku_barcode_or_unit_identifier_and_the_same_request_sku(): void
    {
        ProductDefinition::factory()->create(['sku' => '200001', 'barcode' => null]);
        ProductDefinition::factory()->create(['sku' => 'X', 'barcode' => '200002', 'is_active' => false]);
        $store = Store::factory()->create();
        $phone = ProductDefinition::factory()->create(['tracking_mode' => TrackingMode::SERIALIZED]);
        $unit = $this->registerDevice($this->owner, $store, $phone, null, '200003', condition: UnitCondition::NEW);
        $this->assertSame('200003', $unit->identifiers()->value('value'));

        $definition = ($this->save)($this->owner, null, [
            'name' => 'Case',
            'tracking_mode' => 'quantity',
            'sku' => '200004',
        ]);

        $this->assertSame('200005', $definition->barcode);
        $this->assertSame(200005, CatalogBarcodeSequence::query()->value('last_number'));
    }

    #[Test]
    public function boundary_values_and_exhaustion_are_enforced(): void
    {
        CatalogBarcodeSequence::query()->whereKey(1)->update(['last_number' => InternalBarcodeAllocator::MAX - 1]);

        $last = ($this->save)($this->owner, null, ['name' => 'Last', 'tracking_mode' => 'quantity']);
        $this->assertSame('299999', $last->barcode);

        try {
            ($this->save)($this->owner, null, ['name' => 'Overflow', 'tracking_mode' => 'quantity']);
            $this->fail('Expected exhaustion validation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('barcode', $exception->errors());
            $this->assertSame(
                __('catalog.errors.internal_barcode_exhausted'),
                $exception->errors()['barcode'][0],
            );
        }

        $this->assertSame(0, ProductDefinition::query()->where('name', 'Overflow')->count());
    }

    #[Test]
    public function manual_codes_inside_the_range_are_accepted_when_free(): void
    {
        $definition = ($this->save)($this->owner, null, [
            'name' => 'Manual',
            'tracking_mode' => 'quantity',
            'barcode' => '250000',
        ]);

        $this->assertSame('250000', $definition->barcode);
        $this->assertSame(200000, CatalogBarcodeSequence::query()->value('last_number'));
    }

    #[Test]
    public function in_range_identifiers_that_name_a_different_target_are_rejected(): void
    {
        ProductDefinition::factory()->create(['sku' => '210000', 'barcode' => null]);
        ProductDefinition::factory()->create(['sku' => 'Y', 'barcode' => '210001']);
        $store = Store::factory()->create();
        $phone = ProductDefinition::factory()->create(['tracking_mode' => TrackingMode::SERIALIZED]);
        $this->registerDevice($this->owner, $store, $phone, null, '210002', condition: UnitCondition::NEW);

        $this->assertThrows(
            fn () => ($this->save)($this->owner, null, ['name' => 'A', 'tracking_mode' => 'quantity', 'barcode' => '210000']),
            ValidationException::class,
        );
        $this->assertThrows(
            fn () => ($this->save)($this->owner, null, ['name' => 'B', 'tracking_mode' => 'quantity', 'sku' => '210001']),
            ValidationException::class,
        );
        $this->assertThrows(
            fn () => ($this->save)($this->owner, null, ['name' => 'C', 'tracking_mode' => 'quantity', 'barcode' => '210002']),
            ValidationException::class,
        );

        $outside = ($this->save)($this->owner, null, [
            'name' => 'Legacy pattern',
            'tracking_mode' => 'quantity',
            'barcode' => '210000-x',
        ]);
        $this->assertSame('210000-x', $outside->barcode);
    }

    #[Test]
    public function ordinary_edit_of_a_blank_legacy_product_does_not_generate_a_barcode(): void
    {
        $definition = ProductDefinition::factory()->create([
            'name' => 'Legacy cable',
            'tracking_mode' => TrackingMode::QUANTITY,
            'barcode' => null,
            'sku' => null,
        ]);

        $saved = ($this->save)($this->owner, $definition, [
            'name' => 'Legacy cable renamed',
            'tracking_mode' => 'quantity',
            'selling_price' => '25',
            'barcode' => '',
        ]);

        $this->assertNull($saved->fresh()->barcode);
        $this->assertSame('Legacy cable renamed', $saved->name);
        $this->assertSame('25.00', $saved->selling_price);
        $this->assertSame(200000, CatalogBarcodeSequence::query()->value('last_number'));
    }

    #[Test]
    public function generate_action_is_idempotent_and_stale_blank_edits_cannot_clear_it(): void
    {
        $definition = ProductDefinition::factory()->create([
            'name' => 'Adapter',
            'tracking_mode' => TrackingMode::QUANTITY,
            'barcode' => null,
        ]);

        $first = ($this->generate)($this->owner, $definition);
        $second = ($this->generate)($this->owner, $definition->fresh());

        $this->assertSame('200001', $first->barcode);
        $this->assertSame('200001', $second->barcode);
        $this->assertSame(200001, CatalogBarcodeSequence::query()->value('last_number'));

        $stale = ($this->save)($this->owner, $definition->fresh(), [
            'name' => 'Adapter',
            'tracking_mode' => 'quantity',
            'barcode' => '',
            'expected_barcode' => null,
        ]);

        $this->assertSame('200001', $stale->fresh()->barcode);
    }

    #[Test]
    public function intentional_clear_from_a_fresh_form_removes_the_barcode(): void
    {
        $definition = ProductDefinition::factory()->create([
            'name' => 'Clearable',
            'tracking_mode' => TrackingMode::QUANTITY,
            'barcode' => '200010',
        ]);

        $saved = ($this->save)($this->owner, $definition, [
            'name' => 'Clearable',
            'tracking_mode' => 'quantity',
            'barcode' => '',
            'expected_barcode' => '200010',
        ]);

        $this->assertNull($saved->fresh()->barcode);
    }

    #[Test]
    public function unrelated_edit_keeps_legacy_in_band_ambiguity_but_new_conflicts_fail(): void
    {
        $legacySku = ProductDefinition::factory()->create([
            'name' => 'Legacy SKU holder',
            'sku' => '210000',
            'barcode' => null,
        ]);
        $legacyBarcode = ProductDefinition::factory()->create([
            'name' => 'Legacy barcode holder',
            'sku' => 'LEGACY-B',
            'barcode' => '210000',
        ]);

        $renamed = ($this->save)($this->owner, $legacyBarcode, [
            'name' => 'Legacy barcode holder renamed',
            'tracking_mode' => 'quantity',
            'sku' => 'LEGACY-B',
            'barcode' => '210000',
            'expected_barcode' => '210000',
        ]);

        $this->assertSame('Legacy barcode holder renamed', $renamed->name);
        $this->assertSame('210000', $renamed->fresh()->barcode);
        $this->assertSame('210000', $legacySku->fresh()->sku);

        $this->assertThrows(
            fn () => ($this->save)($this->owner, null, [
                'name' => 'New conflict',
                'tracking_mode' => 'quantity',
                'barcode' => '210000',
            ]),
            ValidationException::class,
        );
    }

    #[Test]
    public function create_request_key_replays_blank_and_manufacturer_submissions_before_uniqueness_checks(): void
    {
        $blankKey = (string) Str::uuid();

        $firstBlank = ($this->save)($this->owner, null, [
            'name' => 'Replay cable',
            'tracking_mode' => 'quantity',
            'sku' => 'ANK-REPLAY',
        ], $blankKey);

        $replayBlank = ($this->save)($this->owner, null, [
            'name' => 'Replay cable',
            'tracking_mode' => 'quantity',
            'sku' => 'ANK-REPLAY',
        ], $blankKey);

        $this->assertTrue($firstBlank->is($replayBlank));
        $this->assertSame($firstBlank->id, $replayBlank->id);
        $this->assertSame('200001', $replayBlank->barcode);
        $this->assertSame('ANK-REPLAY', $replayBlank->sku);
        $this->assertSame(1, ProductDefinition::query()->where('sku', 'ANK-REPLAY')->count());

        $manufacturerKey = (string) Str::uuid();

        $firstManufacturer = ($this->save)($this->owner, null, [
            'name' => 'Manufacturer cable',
            'tracking_mode' => 'quantity',
            'barcode' => '0012345678905',
        ], $manufacturerKey);

        $replayManufacturer = ($this->save)($this->owner, null, [
            'name' => 'Manufacturer cable',
            'tracking_mode' => 'quantity',
            'barcode' => '0012345678905',
        ], $manufacturerKey);

        $this->assertTrue($firstManufacturer->is($replayManufacturer));
        $this->assertSame($firstManufacturer->id, $replayManufacturer->id);
        $this->assertSame('0012345678905', $replayManufacturer->barcode);
        $this->assertSame(1, ProductDefinition::query()->where('barcode', '0012345678905')->count());

        $this->assertThrows(
            fn () => ($this->save)($this->owner, null, [
                'name' => 'Different cable',
                'tracking_mode' => 'quantity',
                'sku' => 'ANK-REPLAY',
            ], $blankKey),
            IdempotencyConflictException::class,
        );

        $other = ($this->save)($this->owner, null, [
            'name' => 'Second cable',
            'tracking_mode' => 'quantity',
        ], (string) Str::uuid());

        $this->assertSame('200002', $other->barcode);
        $this->assertSame(3, ProductDefinition::count());
    }

    #[Test]
    public function unauthorized_users_cannot_generate_internal_barcodes(): void
    {
        $definition = ProductDefinition::factory()->create(['barcode' => null]);
        $employee = User::factory()->create()->assignRole('employee');

        $this->assertThrows(
            fn () => ($this->generate)($employee, $definition),
            AuthorizationException::class,
        );
        $this->assertNull($definition->fresh()->barcode);
    }

    #[Test]
    public function catalog_ui_reveals_generated_codes_in_arabic_and_english_and_reopens_with_the_same_number(): void
    {
        config(['app.locale' => 'ar']);
        App::setLocale('ar');

        Livewire::actingAs($this->owner)
            ->test(DefinitionsIndex::class)
            ->call('openCreateForm')
            ->set('name', 'واقي شاشة')
            ->set('trackingMode', 'quantity')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('barcodeRevealOpen', true)
            ->assertSet('revealedBarcode', '200001')
            ->assertSet('revealedName', 'واقي شاشة')
            ->assertSee('اكتب هذا الرقم على الملصق')
            ->assertSee('متابعة')
            ->assertSee('200001')
            ->assertSeeHtml('dir="rtl"')
            ->assertSeeHtml('dir="ltr"')
            ->call('dismissBarcodeReveal')
            ->assertSet('barcodeRevealOpen', false);

        $this->useEnglishLocale();

        $blank = ProductDefinition::factory()->create(['name' => 'Blank case', 'barcode' => null]);

        Livewire::actingAs($this->owner)
            ->test(DefinitionsIndex::class)
            ->call('openEditForm', $blank->id)
            ->assertSee('Generate internal number')
            ->call('generateInternalBarcode')
            ->assertSet('barcode', '200002')
            ->assertSet('revealedBarcode', '200002')
            ->assertSee('Write this number on the sticker')
            ->assertSee('Continue')
            ->call('generateInternalBarcode')
            ->assertSet('revealedBarcode', '200002');

        $arabic = ProductDefinition::query()->where('barcode', '200001')->sole();

        Livewire::actingAs($this->owner)
            ->test(DefinitionsIndex::class)
            ->call('openEditForm', $arabic->id)
            ->assertSet('barcode', '200001')
            ->assertDontSee('Generate internal number');

        $this->assertSame('200002', $blank->fresh()->barcode);
    }

    #[Test]
    public function end_to_end_blank_accessory_is_found_at_the_till_by_its_internal_number(): void
    {
        $store = Store::factory()->create(['code' => 'KT01']);
        $cashier = User::factory()->create()->assignRole('employee');
        $store->employees()->attach($cashier);
        $cashier->givePermissionTo(OperationalStoreSetupSeeder::USE_POS_PERMISSION);

        $component = Livewire::actingAs($this->owner)
            ->test(DefinitionsIndex::class)
            ->call('openCreateForm')
            ->set('name', 'Tempered glass')
            ->set('trackingMode', 'quantity')
            ->set('sellingPrice', '50')
            ->call('save')
            ->assertSet('revealedBarcode', '200001');

        $definition = ProductDefinition::query()->where('barcode', '200001')->sole();
        app(RecordOpeningBalance::class)($this->owner, $store, $definition, 3, null, (string) Str::uuid());

        Livewire::actingAs($cashier)
            ->test(PosCounter::class, ['store' => $store])
            ->call('scan', '200001')
            ->assertSet('cart.d'.$definition->id.'.quantity', 1)
            ->assertSet('cart.d'.$definition->id.'.name', 'Tempered glass');

        Livewire::actingAs($this->owner)
            ->test(DefinitionsIndex::class)
            ->call('openEditForm', $definition->id)
            ->assertSet('barcode', '200001');

        $this->assertSame(0, InventoryUnitIdentifier::query()->count());
        $this->assertSame($component->get('revealedBarcode'), $definition->fresh()->barcode);
    }
}
