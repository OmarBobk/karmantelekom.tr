<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\Pos\PosRollbackBlockedException;
use App\Services\Pos\PosRollbackGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\InteractsWithPos;
use Tests\TestCase;

class PosRollbackGuardTest extends TestCase
{
    use InteractsWithPos;
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function phaseFiveMigrations(): array
    {
        return collect([
            '2026_11_06_190001_add_selling_price_to_product_definitions_table',
            '2026_11_06_190002_add_selling_price_to_inventory_units_table',
            '2026_11_06_190003_create_card_terminals_table',
            '2026_11_06_190004_create_checkout_attempts_table',
            '2026_11_06_190005_create_card_approvals_table',
            '2026_11_06_190006_create_sale_number_sequences_table',
            '2026_11_06_190007_create_sales_table',
            '2026_11_06_190008_create_sale_lines_table',
            '2026_11_06_190009_create_sale_payments_table',
        ])->mapWithKeys(fn (string $migration): array => [$migration => [$migration]])->all();
    }

    #[Test]
    #[DataProvider('phaseFiveMigrations')]
    public function rolling_back_any_phase_five_migration_with_sales_is_refused_before_any_schema_change(string $migration): void
    {
        $this->setUpCounter();
        $cashier = $this->createCashier();
        $this->finalize($cashier, $this->review($cashier, [...$this->cableLine(1), ...$this->phoneLine()]), '12549.90');
        $schemaBefore = $this->posSchema();

        try {
            $this->migration($migration)->down();
            $this->fail('The rollback should have been refused.');
        } catch (PosRollbackBlockedException $exception) {
            $this->assertSame(1, $exception->recordedData['sales']);
            $this->assertSame(2, $exception->recordedData['inventory_movements (sales)']);
            $this->assertSame(1, $exception->recordedData['inventory_units (sold)']);
        }

        $this->assertSame($schemaBefore, $this->posSchema());
        $this->assertSame(1, DB::table('sales')->count());
    }

    #[Test]
    public function an_empty_counter_does_not_block_a_rollback(): void
    {
        $this->assertSame([], app(PosRollbackGuard::class)->recordedPosData());
    }

    #[Test]
    public function a_selling_price_alone_blocks_a_rollback(): void
    {
        $this->setUpCounter();
        DB::table('inventory_units')->update(['selling_price' => null]);

        $this->assertSame(['product_definitions (selling price set)' => 1], app(PosRollbackGuard::class)->recordedPosData());
    }

    private function migration(string $name): Migration
    {
        return require database_path("migrations/{$name}.php");
    }

    /**
     * @return array{tables: list<bool>, definition_price: bool, unit_price: bool}
     */
    private function posSchema(): array
    {
        return [
            'tables' => array_map(fn (string $table): bool => Schema::hasTable($table), PosRollbackGuard::TABLES),
            'definition_price' => Schema::hasColumn('product_definitions', 'selling_price'),
            'unit_price' => Schema::hasColumn('inventory_units', 'selling_price'),
        ];
    }
}
