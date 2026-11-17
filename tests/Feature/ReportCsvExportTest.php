<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Customers\RecordCustomerPayment;
use App\Actions\Expenses\SubmitExpense;
use App\Actions\Pos\ReviewCheckout;
use App\Enums\Currency;
use App\Enums\ExpenseCategory;
use App\Enums\ExpenseMethod;
use App\Livewire\Pos\SalesHistory;
use App\Models\Customer;
use App\Models\OperationalSupplier;
use App\Models\Store;
use App\Models\User;
use App\Services\Purchasing\Money;
use App\Services\Reporting\CsvExporter;
use App\Services\Reporting\ReportCsvBatch;
use App\Services\Reporting\SalesCsv;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
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
    public function sales_http_export_matches_filtered_history_across_batches_with_safe_csv_cells(): void
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

        $csv = ReportCsvBatch::using(10, fn (): string => $this->download(
            route('pos.sales.export', [$this->store, 'from' => '2026-11-10', 'to' => '2026-11-10']),
        ));
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
    }

    #[Test]
    public function expense_customer_and_supplier_http_exports_reuse_filters_and_refuse_unauthorized_callers(): void
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

        $expenseCsv = $this->parseCsv($this->download(route('pos.expenses.export', [
            $this->store,
            'from' => '2026-11-10',
            'to' => '2026-11-10',
            'category' => ExpenseCategory::SUPPLIES->value,
        ])));
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

        $openCsv = $this->parseCsv($this->download(route('pos.customers.export', [$this->store, 'balance' => 'open'])));
        $this->assertTrue(collect($openCsv)->contains(
            fn (array $row): bool => ($row[0] ?? '') === 'Open Debtor' && ($row[1] ?? '') === '905320000001' && ($row[2] ?? '') === '20.00'
        ));

        $settledCsv = $this->parseCsv($this->download(route('pos.customers.export', [$this->store, 'balance' => 'settled'])));
        $this->assertTrue(collect($settledCsv)->contains(
            fn (array $row): bool => ($row[0] ?? '') === 'Settled Debtor' && ($row[2] ?? '') === '0.00' && ($row[4] ?? '') === 'Settled'
        ));

        $anka = OperationalSupplier::factory()->create(['name' => 'Anka Toptan']);
        $this->postedPurchase($this->owner, $this->store, $anka, [[$this->cable, 2, '10.00']], ['supplier_invoice_number' => 'TRY-1']);
        $this->postedPurchase($this->owner, $this->second, $anka, [[$this->cable, 1, '5.00']], ['supplier_invoice_number' => 'USD-1'], Currency::USD);

        $supplierCsv = $this->parseCsv($this->download(route('subdomain.operations.suppliers.export')));
        $body = array_slice($supplierCsv, 1);
        $this->assertCount(2, $body);
        $this->assertSame(['TRY', 'USD'], collect($body)->pluck(1)->sort()->values()->all());
        $this->assertSame('20.00', collect($body)->firstWhere(1, 'TRY')[2]);
        $this->assertSame('5.00', collect($body)->firstWhere(1, 'USD')[2]);

        auth()->logout();
        $this->get(route('pos.sales.export', $this->store))->assertRedirect();
        $this->actingAs($this->cashier)->get(route('pos.sales.export', [$this->store, 'from' => '2026-11-10', 'to' => '2026-11-10']))->assertForbidden();
        $this->actingAs($this->cashier)->get(route('pos.expenses.export', $this->store))->assertForbidden();
        $this->actingAs($this->cashier)->get(route('pos.customers.export', $this->store))->assertForbidden();
        $this->actingAs($this->cashier)->get(route('subdomain.operations.suppliers.export'))->assertRedirect();

        $outsider = $this->createCashier($this->second, withPos: true, name: 'Other branch');
        $this->actingAs($outsider)->get(route('pos.sales.export', [$this->store, 'from' => '2026-11-10', 'to' => '2026-11-10']))->assertForbidden();

        $this->actingAs($this->owner)->get(route('pos.sales.export', [$this->store, 'from' => '2026-13-01', 'to' => '2026-13-02']))->assertStatus(422);
        $this->actingAs($this->owner)->get(route('pos.expenses.export', [
            $this->store,
            'from' => '2026-11-10',
            'to' => '2026-11-10',
            'category' => 'not-a-category',
        ]))->assertStatus(422);
    }

    #[Test]
    public function export_endpoints_reauthorize_after_permission_revocation(): void
    {
        $this->actingAs($this->owner)
            ->get(route('pos.sales.export', [$this->store, 'from' => '2026-11-10', 'to' => '2026-11-10']))
            ->assertOk();

        $this->revokeFromOwners('use_pos');
        $this->actingAs($this->owner->fresh())
            ->get(route('pos.sales.export', [$this->store, 'from' => '2026-11-10', 'to' => '2026-11-10']))
            ->assertForbidden();
        $this->restoreOwnerPermission('use_pos');

        $this->actingAs($this->owner->fresh())
            ->get(route('pos.expenses.export', [$this->store, 'from' => '2026-11-10', 'to' => '2026-11-10']))
            ->assertOk();
        $this->revokeFromOwners('manage_expenses');
        $this->actingAs($this->owner->fresh())
            ->get(route('pos.expenses.export', [$this->store, 'from' => '2026-11-10', 'to' => '2026-11-10']))
            ->assertForbidden();

        $this->actingAs($this->owner->fresh())
            ->get(route('pos.customers.export', $this->store))
            ->assertOk();
        $this->revokeFromOwners('receive_customer_payments');
        $this->actingAs($this->owner->fresh())
            ->get(route('pos.customers.export', $this->store))
            ->assertForbidden();

        $this->actingAs($this->owner->fresh())
            ->get(route('subdomain.operations.suppliers.export'))
            ->assertOk();
        $this->revokeFromOwners('manage_purchases');
        $this->revokeFromOwners('pay_suppliers');
        $this->actingAs($this->owner->fresh())
            ->get(route('subdomain.operations.suppliers.export'))
            ->assertForbidden();
    }

    #[Test]
    public function arabic_session_locale_localizes_sales_export_headers(): void
    {
        $this->finalize($this->cashier, $this->review($this->cashier, $this->cableLine(1)), '50');

        $csv = $this->parseCsv($this->withSession(['pos_locale' => 'ar'])->actingAs($this->owner)->get(
            route('pos.sales.export', [$this->store, 'from' => '2026-11-10', 'to' => '2026-11-10']),
        )->assertOk()->streamedContent());

        $this->assertSame('رقم البيع', $csv[0][0]);
        $this->assertSame('وقت الإتمام (إسطنبول)', $csv[0][1]);
    }

    #[Test]
    public function sales_export_related_queries_grow_per_batch_not_per_row(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->finalize($this->cashier, $this->review($this->cashier, $this->cableLine(1)), '50');
        }

        $filtered = SalesCsv::query($this->owner, $this->store, '', '2026-11-10', '2026-11-10');

        $cashierLoadsFor = function (int $batch) use ($filtered): int {
            return ReportCsvBatch::using($batch, function () use ($filtered): int {
                DB::flushQueryLog();
                DB::enableQueryLog();
                iterator_to_array(SalesCsv::rows($filtered->clone()));
                $log = collect(DB::getQueryLog());
                DB::disableQueryLog();

                return $log->filter(fn (array $query): bool => str_contains(strtolower($query['query']), 'from `users`')
                    && str_contains(strtolower($query['query']), ' in ('))->count();
            });
        };

        $this->assertSame(3, $cashierLoadsFor(2));
        $this->assertSame(1, $cashierLoadsFor(5));
        $this->assertSame(5, $cashierLoadsFor(1));
    }

    #[Test]
    public function csv_escaping_and_formula_neutralization_round_trip_through_standard_reader(): void
    {
        $this->assertSame("'=1+1", CsvExporter::text('=1+1'));
        $this->assertSame("'+paid", CsvExporter::text('+paid'));
        $this->assertSame("'-10 sneak", CsvExporter::text('-10 sneak'));
        $this->assertSame("'@cmd", CsvExporter::text('@cmd'));
        $this->assertSame("'\t=hidden", CsvExporter::text("\t=hidden"));
        $this->assertSame("'\n=1+1", CsvExporter::text("\n=1+1"));
        $this->assertSame("'\r\n@SUM(A1)", CsvExporter::text("\r\n@SUM(A1)"));
        $this->assertSame("'  =padded", CsvExporter::text('  =padded'));
        $this->assertSame("'".chr(1).'=ctrl', CsvExporter::text(chr(1).'=ctrl'));
        $this->assertSame("hello\nworld", CsvExporter::text("hello\nworld"));
        $this->assertSame('12.50', CsvExporter::amount(Money::toDecimal(1250)));
        $this->assertSame('ordinary', CsvExporter::text('ordinary'));

        $handle = fopen('php://memory', 'r+');
        $this->assertNotFalse($handle);
        fwrite($handle, "\xEF\xBB\xBF");
        CsvExporter::writeRow($handle, ['Sale', 'Note', 'Amount', 'Id']);
        CsvExporter::writeRow($handle, [
            CsvExporter::text('a,b'),
            CsvExporter::text('say "hi"'),
            CsvExporter::amount('12.50'),
            CsvExporter::text('00123'),
        ]);
        CsvExporter::writeRow($handle, [
            CsvExporter::text('end\\'),
            CsvExporter::text('quote\\"tail'),
            CsvExporter::amount('0.00'),
            CsvExporter::text(''),
        ]);
        CsvExporter::writeRow($handle, [
            CsvExporter::text("line1\r\nline2"),
            CsvExporter::text('عميل'),
            CsvExporter::amount('100.00'),
            CsvExporter::text("'\n=1+1"),
        ]);
        rewind($handle);

        $bom = fread($handle, 3);
        $this->assertSame("\xEF\xBB\xBF", $bom);

        $rows = [];
        while (($row = fgetcsv($handle, null, CsvExporter::DELIMITER, CsvExporter::ENCLOSURE, CsvExporter::ESCAPE)) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        $this->assertSame(['Sale', 'Note', 'Amount', 'Id'], $rows[0]);
        $this->assertSame(['a,b', 'say "hi"', '12.50', '00123'], $rows[1]);
        $this->assertSame(['end\\', 'quote\\"tail', '0.00', ''], $rows[2]);
        $this->assertSame(["line1\r\nline2", 'عميل', '100.00', "'\n=1+1"], $rows[3]);
    }

    /**
     * @return list<list<string|null>>
     */
    private function parseCsv(string $csv): array
    {
        $csv = Str::chopStart($csv, "\xEF\xBB\xBF");
        $handle = fopen('php://memory', 'r+');
        $this->assertNotFalse($handle);
        fwrite($handle, $csv);
        rewind($handle);

        $rows = [];

        while (($row = fgetcsv($handle, null, CsvExporter::DELIMITER, CsvExporter::ENCLOSURE, CsvExporter::ESCAPE)) !== false) {
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    private function download(string $url): string
    {
        /** @var TestResponse $response */
        $response = $this->actingAs($this->owner)->get($url);
        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        return $response->streamedContent();
    }

    private function restoreOwnerPermission(string $permission): void
    {
        Role::findByName('admin')->givePermissionTo($permission);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
