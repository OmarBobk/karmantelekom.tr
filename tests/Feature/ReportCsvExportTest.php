<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Customers\RecordCustomerPayment;
use App\Actions\Expenses\SubmitExpense;
use App\Actions\Pos\ReviewCheckout;
use App\Enums\Currency;
use App\Enums\ExpenseCategory;
use App\Enums\ExpenseMethod;
use App\Livewire\Backend\Reports\FinanceOverview;
use App\Livewire\Pos\CustomerAccounts;
use App\Livewire\Pos\ExpensesPage;
use App\Livewire\Pos\SalesHistory;
use App\Models\Customer;
use App\Models\OperationalSupplier;
use App\Models\Store;
use App\Models\User;
use App\Services\Purchasing\Money;
use App\Services\Reporting\CsvExporter;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\InteractsWithPos;
use Tests\Support\InteractsWithPurchasing;
use Tests\TestCase;

class ReportCsvExportTest extends TestCase
{
    use InteractsWithPos;
    use InteractsWithPurchasing;
    use RefreshDatabase;

    private Store $second;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCounter(200);
        $this->useEnglishLocale();
        $this->second = Store::factory()->create(['code' => 'KT02', 'name' => 'Karman Aksaray']);
        $this->cashier = $this->createCashier();
        $this->travelTo(CarbonImmutable::parse('2026-11-10 12:00:00', 'UTC'));
    }

    #[Test]
    public function sales_export_matches_the_filtered_history_across_pages_with_safe_csv_cells(): void
    {
        $customer = Customer::factory()->atCounter($this->store, '05321234567')->create(['name' => '=Danger Customer']);
        $numbers = [];

        for ($i = 0; $i < 26; $i++) {
            $numbers[] = $this->finalize($this->cashier, $this->review($this->cashier, $this->cableLine(1)), '50')->number;
        }

        $edge = $this->finalize(
            $this->owner,
            app(ReviewCheckout::class)($this->owner, $this->store, $this->cableLine(1), null, null, $customer->id),
            '50',
        );
        DB::table('sales')->where('id', $edge->id)->update([
            'completed_at' => CarbonImmutable::parse('2026-11-09 21:00:00', 'UTC'),
            'number' => 'KT01-000099',
        ]);
        $numbers[] = 'KT01-000099';

        $before = $this->finalize($this->cashier, $this->review($this->cashier, $this->cableLine(1)), '50');
        DB::table('sales')->where('id', $before->id)->update([
            'completed_at' => CarbonImmutable::parse('2026-11-09 20:59:59', 'UTC'),
        ]);

        $history = Livewire::actingAs($this->owner)
            ->withQueryParams(['from' => '2026-11-10', 'to' => '2026-11-10'])
            ->test(SalesHistory::class, ['store' => $this->store]);

        $this->assertSame(27, $history->viewData('summary')['count']);
        $this->assertCount(25, $history->viewData('sales'));

        $csv = $this->downloadCsv($history, 'export');
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $rows = $this->parseCsv($csv);

        $this->assertSame([
            'Sale number', 'Completed at (Istanbul)', 'Cashier', 'Customer', 'Customer phone',
            'Lines', 'Total', 'Paid', 'Credit', 'Currency',
        ], $rows[0]);
        $this->assertCount(28, $rows);

        $exportedNumbers = array_column(array_slice($rows, 1), 0);
        $expected = $numbers;
        sort($expected);
        $sortedExport = $exportedNumbers;
        sort($sortedExport);
        $this->assertSame($expected, $sortedExport);
        $this->assertNotContains($before->number, $exportedNumbers);

        $danger = collect($rows)->first(fn (array $row): bool => ($row[3] ?? '') === "'=Danger Customer");
        $this->assertNotNull($danger);
        $this->assertSame('905321234567', $danger[4]);
        $this->assertSame('49.90', $danger[6]);
        $this->assertSame('TRY', $danger[9]);
        $this->assertSame('2026-11-10 00:00', $danger[1]);

        Livewire::actingAs($this->cashier)
            ->test(SalesHistory::class, ['store' => $this->store])
            ->call('export')
            ->assertForbidden();
    }

    #[Test]
    public function expense_customer_and_supplier_exports_reuse_screen_filters_and_refuse_non_owners(): void
    {
        app(SubmitExpense::class)($this->owner, $this->store, null, [
            'method' => ExpenseMethod::CARD->value,
            'category' => ExpenseCategory::SUPPLIES->value,
            'description' => "+bonus\nline",
            'amount' => '125.50',
            'expense_date' => '2026-11-10',
            'reference' => 'REF-001',
            'operational_supplier_id' => null,
            'inventory_unit_id' => null,
        ], (string) Str::uuid());
        app(SubmitExpense::class)($this->owner, $this->store, null, [
            'method' => ExpenseMethod::CARD->value,
            'category' => ExpenseCategory::RENT->value,
            'description' => 'Rent',
            'amount' => '1000.00',
            'expense_date' => '2026-11-09',
            'reference' => null,
            'operational_supplier_id' => null,
            'inventory_unit_id' => null,
        ], (string) Str::uuid());

        $expenses = Livewire::actingAs($this->owner)
            ->withQueryParams(['from' => '2026-11-10', 'to' => '2026-11-10', 'category' => ExpenseCategory::SUPPLIES->value])
            ->test(ExpensesPage::class, ['store' => $this->store]);
        $expenseCsv = $this->parseCsv($this->downloadCsv($expenses, 'exportHistory'));
        $this->assertCount(2, $expenseCsv);
        $this->assertSame('2026-11-10', $expenseCsv[1][0]);
        $this->assertSame(ExpenseCategory::SUPPLIES->label(), $expenseCsv[1][1]);
        $this->assertSame("'+bonus\nline", $expenseCsv[1][2]);
        $this->assertSame('125.50', $expenseCsv[1][3]);
        $this->assertSame('TRY', $expenseCsv[1][4]);

        $open = Customer::factory()->atCounter($this->store, '05320000001')->create(['name' => 'Open Debtor']);
        $settled = Customer::factory()->atCounter($this->store, '05320000002')->create(['name' => 'Settled Debtor']);
        $this->creditSale($open, 1, '20.00', '29.90');
        $this->creditSale($settled, 1, '20.00', '29.90');
        app(RecordCustomerPayment::class)($this->owner, $this->store, $settled, [
            'amount' => '20.00',
            'method' => 'bank_transfer',
            'reference' => 'CLR-1',
        ], (string) Str::uuid());

        $openCsv = $this->parseCsv($this->downloadCsv(
            Livewire::actingAs($this->owner)->test(CustomerAccounts::class, ['store' => $this->store]),
            'export',
        ));
        $this->assertTrue(collect($openCsv)->contains(
            fn (array $row): bool => ($row[0] ?? '') === 'Open Debtor' && ($row[1] ?? '') === '905320000001' && ($row[2] ?? '') === '20.00'
        ));

        $settledCsv = $this->parseCsv($this->downloadCsv(
            Livewire::actingAs($this->owner)->withQueryParams(['balance' => 'settled'])->test(CustomerAccounts::class, ['store' => $this->store]),
            'export',
        ));
        $this->assertTrue(collect($settledCsv)->contains(
            fn (array $row): bool => ($row[0] ?? '') === 'Settled Debtor' && ($row[2] ?? '') === '0.00' && ($row[4] ?? '') === 'Settled'
        ));

        $anka = OperationalSupplier::factory()->create(['name' => 'Anka Toptan']);
        $this->postedPurchase($this->owner, $this->store, $anka, [[$this->cable, 2, '10.00']], ['supplier_invoice_number' => 'TRY-1']);
        $this->postedPurchase($this->owner, $this->second, $anka, [[$this->cable, 1, '5.00']], ['supplier_invoice_number' => 'USD-1'], Currency::USD);

        $finance = Livewire::actingAs($this->owner)
            ->withQueryParams(['from' => '2026-11-10', 'to' => '2026-11-10'])
            ->test(FinanceOverview::class);
        $supplierCsv = $this->parseCsv($this->downloadCsv($finance, 'exportSuppliers'));
        $body = array_slice($supplierCsv, 1);
        $this->assertCount(2, $body);
        $this->assertSame(['TRY', 'USD'], collect($body)->pluck(1)->sort()->values()->all());
        $this->assertSame('20.00', collect($body)->firstWhere(1, 'TRY')[2]);
        $this->assertSame('5.00', collect($body)->firstWhere(1, 'USD')[2]);

        Livewire::actingAs($this->cashier)->test(ExpensesPage::class, ['store' => $this->store])->assertForbidden();
        Livewire::actingAs($this->cashier)->test(CustomerAccounts::class, ['store' => $this->store])->assertForbidden();
        Livewire::actingAs($this->cashier)->test(FinanceOverview::class)->assertForbidden();
    }

    #[Test]
    public function csv_text_neutralizes_formula_prefixes_and_leaves_plain_decimals(): void
    {
        $this->assertSame("'=1+1", CsvExporter::text('=1+1'));
        $this->assertSame("'+paid", CsvExporter::text('+paid'));
        $this->assertSame("'-10 sneak", CsvExporter::text('-10 sneak'));
        $this->assertSame("'@cmd", CsvExporter::text('@cmd'));
        $this->assertSame("'	=hidden", CsvExporter::text("\t=hidden"));
        $this->assertSame('12.50', CsvExporter::amount(Money::toDecimal(1250)));
        $this->assertSame('ordinary', CsvExporter::text('ordinary'));
    }

    /**
     * @return list<list<string|null>>
     */
    private function parseCsv(string $csv): array
    {
        $csv = Str::chopStart($csv, "\xEF\xBB\xBF");
        $handle = fopen('php://memory', 'r+');
        fwrite($handle, $csv);
        rewind($handle);

        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    private function downloadCsv(Testable $component, string $action): string
    {
        $component->call($action)->assertFileDownloaded();

        return base64_decode((string) data_get($component->effects, 'download.content'), true) ?: '';
    }
}
