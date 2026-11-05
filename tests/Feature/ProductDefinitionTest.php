<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CatalogIdentifierType;
use App\Enums\TrackingMode;
use App\Models\ProductDefinition;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ValueError;

class ProductDefinitionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function lookup_is_explicit_about_identifier_type(): void
    {
        $bySku = ProductDefinition::factory()->create(['sku' => '8690001']);
        $byBarcode = ProductDefinition::factory()->create(['barcode' => '8690001']);

        $this->assertTrue($bySku->is(ProductDefinition::findByIdentifier(CatalogIdentifierType::SKU, '8690001')));
        $this->assertTrue($byBarcode->is(ProductDefinition::findByIdentifier(CatalogIdentifierType::BARCODE, '8690001')));
        $this->assertNull(ProductDefinition::findByIdentifier(CatalogIdentifierType::SKU, 'UNKNOWN'));
    }

    #[Test]
    public function lookup_matches_exact_identifiers_after_trimming(): void
    {
        $definition = ProductDefinition::factory()->create(['sku' => 'CBL-USBC-1M', 'barcode' => '0012345']);

        $this->assertTrue($definition->is(ProductDefinition::findByIdentifier(CatalogIdentifierType::SKU, '  CBL-USBC-1M ')));
        $this->assertTrue($definition->is(ProductDefinition::findByIdentifier(CatalogIdentifierType::BARCODE, "0012345\t")));

        $this->assertNull(ProductDefinition::findByIdentifier(CatalogIdentifierType::SKU, 'cbl-usbc-1m'));
        $this->assertNull(ProductDefinition::findByIdentifier(CatalogIdentifierType::SKU, 'CBL-USBC'));
        $this->assertNull(ProductDefinition::findByIdentifier(CatalogIdentifierType::BARCODE, '12345'));
        $this->assertNull(ProductDefinition::findByIdentifier(CatalogIdentifierType::BARCODE, 12345));
    }

    #[Test]
    public function blank_identifiers_are_stored_as_absent_and_never_match(): void
    {
        ProductDefinition::factory()->create(['sku' => '   ', 'barcode' => '']);
        ProductDefinition::factory()->create(['sku' => null, 'barcode' => "\n"]);

        $this->assertSame(2, ProductDefinition::whereNull('sku')->whereNull('barcode')->count());
        $this->assertNull(ProductDefinition::findByIdentifier(CatalogIdentifierType::SKU, ''));
        $this->assertNull(ProductDefinition::findByIdentifier(CatalogIdentifierType::BARCODE, '  '));
        $this->assertNull(ProductDefinition::findByIdentifier(CatalogIdentifierType::BARCODE, null));
    }

    #[Test]
    public function identifiers_are_trimmed_and_barcodes_keep_leading_zeros(): void
    {
        $definition = ProductDefinition::factory()->create(['sku' => '  CHG-20W ', 'barcode' => ' 0001234567890 ']);

        $this->assertDatabaseHas('product_definitions', ['id' => $definition->id, 'sku' => 'CHG-20W', 'barcode' => '0001234567890']);
        $this->assertSame('0001234567890', $definition->fresh()->barcode);
    }

    #[Test]
    public function duplicate_sku_is_rejected_by_the_database_after_normalization(): void
    {
        ProductDefinition::factory()->create(['sku' => 'CASE-IP15']);

        $this->expectException(UniqueConstraintViolationException::class);

        ProductDefinition::factory()->create(['sku' => ' CASE-IP15 ']);
    }

    #[Test]
    public function duplicate_barcode_is_rejected_by_the_database_after_normalization(): void
    {
        ProductDefinition::factory()->create(['barcode' => '0012345']);

        $this->expectException(UniqueConstraintViolationException::class);

        ProductDefinition::factory()->create(['barcode' => '0012345 ']);
    }

    #[Test]
    public function identifiers_are_unique_only_within_their_own_type_and_exact_value(): void
    {
        ProductDefinition::factory()->create(['sku' => 'ABC-1', 'barcode' => '0012']);
        ProductDefinition::factory()->create(['sku' => 'abc-1', 'barcode' => '12']);
        ProductDefinition::factory()->create(['sku' => '0012', 'barcode' => 'ABC-1']);

        $this->assertSame(3, ProductDefinition::count());
    }

    /**
     * @return array<string, array{TrackingMode}>
     */
    public static function trackingModes(): array
    {
        return array_combine(
            TrackingMode::values(),
            array_map(fn (TrackingMode $mode): array => [$mode], TrackingMode::cases()),
        );
    }

    #[Test]
    #[DataProvider('trackingModes')]
    public function supported_tracking_modes_round_trip(TrackingMode $mode): void
    {
        $definition = ProductDefinition::create(['name' => 'Definition', 'tracking_mode' => $mode->value]);

        $this->assertSame($mode, $definition->fresh()->tracking_mode);
        $this->assertTrue($definition->fresh()->is_active);
    }

    #[Test]
    public function only_the_three_tracking_modes_exist(): void
    {
        $this->assertSame(['quantity', 'serialized', 'used_device'], TrackingMode::values());
        $this->assertFalse(TrackingMode::QUANTITY->tracksIndividualItems());
        $this->assertTrue(TrackingMode::SERIALIZED->tracksIndividualItems());
        $this->assertTrue(TrackingMode::USED_DEVICE->tracksIndividualItems());
    }

    #[Test]
    public function unknown_tracking_mode_is_rejected(): void
    {
        $this->expectException(ValueError::class);

        ProductDefinition::create(['name' => 'Definition', 'tracking_mode' => 'imei']);
    }

    #[Test]
    public function tracking_mode_is_required(): void
    {
        $this->expectException(QueryException::class);

        ProductDefinition::create(['name' => 'Definition']);
    }
}
