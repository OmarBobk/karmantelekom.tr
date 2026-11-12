<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Cashbox\CloseCashboxSession;
use App\Actions\Cashbox\OpenCashboxSession;
use App\Actions\Cashbox\StartCashboxTransfer;
use App\Actions\Cashbox\StartCustomerCashIntake;
use App\Actions\Customers\RecordCustomerPayment;
use App\Actions\Expenses\ConfirmCashPayout;
use App\Actions\Expenses\ReleaseCashPayout;
use App\Actions\Expenses\SubmitExpense;
use App\Actions\Purchasing\RecordSupplierPayment;
use App\Actions\Purchasing\ReversePurchase;
use App\Actions\Purchasing\ReverseSupplierPayment;
use App\Enums\Currency;
use App\Enums\PurchaseStatus;
use App\Exceptions\Purchasing\InvalidAmountException;
use App\Livewire\Backend\Reports\FinanceOverview;
use App\Livewire\Backend\Reports\OperationsDashboard;
use App\Livewire\Pos\CashboxPage;
use App\Livewire\Pos\CashboxSessionPage;
use App\Livewire\Pos\ExpensesPage;
use App\Models\CashboxSession;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseSubmission;
use App\Models\OperationalSupplier;
use App\Models\Store;
use App\Models\User;
use App\Services\Purchasing\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\InteractsWithPos;
use Tests\Support\InteractsWithPurchasing;
use Tests\TestCase;

class FinanceOverviewTest extends TestCase
{
    use InteractsWithPos;
    use InteractsWithPurchasing;
    use RefreshDatabase;

    private const DAY = ['from' => '2026-11-10', 'to' => '2026-11-10'];

    private Store $second;

    private Store $third;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCounter(100);
        $this->useEnglishLocale();
        $this->second = Store::factory()->create(['code' => 'KT02', 'name' => 'Karman Aksaray']);
        $this->third = Store::factory()->create(['code' => 'KT03', 'name' => 'Karman Empty']);
    }

    #[Test]
    public function period_expenses_reconcile_with_the_expense_history_and_exclude_pending_and_released_payouts(): void
    {
        $this->recordScenario();

        $report = $this->report();
        $expenses = $report->viewData('expenses');

        $this->assertSame(
            ['KT01' => [['supplies' => 20_000], 20_000], 'KT02' => [['rent' => 100_000], 100_000], 'KT03' => [[], 0]],
            collect($expenses['rows'])->mapWithKeys(fn (array $row): array => [$row['store']->code => [$row['categories'], $row['total']]])->all(),
        );
        $this->assertSame([120_000, ['rent' => 100_000, 'supplies' => 20_000]], [$expenses['total'], $expenses['category_totals']]);
        $report->assertSee('1.200,00 TRY')->assertSee(route('pos.expenses', [$this->store, ...self::DAY]));

        $history = Livewire::actingAs($this->owner)->withQueryParams(self::DAY)->test(ExpensesPage::class, ['store' => $this->store]);
        $this->assertSame(['count' => 1, 'total_minor' => 20_000], $history->viewData('historySummary'));
        $history->assertSee('Office paper')->assertDontSee('Electricity');

        $history->set('historyCategory', 'utilities')->set('historyFrom', '2026-11-09')->set('historyTo', '');
        $this->assertSame(['2026-11-09', '2026-11-09', ['count' => 1, 'total_minor' => 5_000]], [$history->get('historyFrom'), $history->get('historyTo'), $history->viewData('historySummary')]);

        $history->set('historyCategory', 'bribes')->assertViewHas('historyError', 'invalid_category')->assertViewHas('historySummary', null);
        $history->set('historyCategory', '')->set('historyFrom', '2026-11-11')->set('historyTo', '2026-11-10')
            ->assertViewHas('historyError', 'reversed')
            ->assertSee('From must be on or before To.');

        $unfiltered = Livewire::actingAs($this->owner)->withQueryParams([])->test(ExpensesPage::class, ['store' => $this->store]);
        $this->assertSame(['count' => 2, 'total_minor' => 25_000], $unfiltered->viewData('historySummary'));
        $unfiltered->call('setPage', 2)->set('historyCategory', 'supplies')->assertSet('paginators.page', 1);

        $previousDay = Livewire::actingAs($this->owner)->withQueryParams(['from' => '2026-11-09', 'to' => '2026-11-09'])->test(FinanceOverview::class);
        $this->assertSame(5_000, $previousDay->viewData('expenses')['total']);
    }

    #[Test]
    public function each_branch_category_amount_links_to_the_expense_history_filtered_to_that_category(): void
    {
        $this->recordScenario();
        $this->travelTo(CarbonImmutable::parse('2026-11-10 12:00:00', 'UTC'));
        $this->expense($this->store, null, 'card', 'marketing', '75', '2026-11-10', 'Flyers');
        $this->travelBack();

        $report = $this->report();
        $kt01 = collect($report->viewData('expenses')['rows'])->firstWhere('store.code', 'KT01');
        $this->assertEquals([['supplies' => 20_000, 'marketing' => 7_500], 27_500], [$kt01['categories'], $kt01['total']]);

        $report
            ->assertSee(route('pos.expenses', [$this->store, ...self::DAY, 'category' => 'supplies']))
            ->assertSee(route('pos.expenses', [$this->store, ...self::DAY, 'category' => 'marketing']))
            ->assertSee(route('pos.expenses', [$this->store, ...self::DAY]))
            ->assertDontSee(route('pos.expenses', [$this->second, ...self::DAY, 'category' => 'supplies']))
            ->assertSee('View the Supplies expenses of KT01 for this period');

        foreach (['supplies' => 20_000, 'marketing' => 7_500] as $category => $figure) {
            $history = Livewire::actingAs($this->owner)
                ->withQueryParams([...self::DAY, 'category' => $category])
                ->test(ExpensesPage::class, ['store' => $this->store]);

            $this->assertSame(['count' => 1, 'total_minor' => $figure], $history->viewData('historySummary'));
            $this->assertSame($kt01['categories'][$category], $history->viewData('historySummary')['total_minor']);
        }
    }

    #[Test]
    public function closed_session_differences_use_stored_closing_differences_on_istanbul_close_dates_only(): void
    {
        $sessions = $this->recordScenario();

        $cashbox = $this->report()->viewData('cashbox');
        $differences = $cashbox['differences'];

        $this->assertSame(
            ['KT01' => [1, 1, 0, 300], 'KT02' => [2, 2, 2_500, 2_000], 'KT03' => [0, 0, 0, 0]],
            collect($differences['rows'])->mapWithKeys(fn (array $row): array => [$row['store']->code => [$row['sessions'], $row['with_difference'], $row['overage'], $row['shortage']]])->all(),
        );
        $this->assertSame([3, 2_500, 2_300, 200], [$differences['sessions'], $differences['overage'], $differences['shortage'], $differences['net']]);
        $this->assertSame(
            [$sessions['c']->id, $sessions['b']->id, $sessions['first']->id],
            $cashbox['sessions']->getCollection()->modelKeys(),
        );
        $this->assertSame(['5.00', '25.00'], [$sessions['b']->opening_difference, $sessions['b']->closing_difference]);

        $this->report()
            ->assertSee('+25,00')
            ->assertSee('+5,00')
            ->assertSee('-20,00')
            ->assertSee('+2,00 TRY')
            ->assertSee(route('pos.cashbox.session', [$this->second, $sessions['b']]))
            ->assertDontSee(route('pos.cashbox.session', [$this->second, $sessions['a']]))
            ->assertDontSee(route('pos.cashbox.session', [$this->second, $sessions['d']]));

        $nextDay = Livewire::actingAs($this->owner)->withQueryParams(['from' => '2026-11-11', 'to' => '2026-11-11'])->test(FinanceOverview::class);
        $this->assertSame([1, 100, 0], [$nextDay->viewData('cashbox')['differences']['sessions'], $nextDay->viewData('cashbox')['differences']['overage'], $nextDay->viewData('cashbox')['differences']['shortage']]);

        $previousDay = Livewire::actingAs($this->owner)->withQueryParams(['from' => '2026-11-09', 'to' => '2026-11-09'])->test(FinanceOverview::class);
        $this->assertSame([1, 0, 1_000], [$previousDay->viewData('cashbox')['differences']['sessions'], $previousDay->viewData('cashbox')['differences']['overage'], $previousDay->viewData('cashbox')['differences']['shortage']]);
    }

    #[Test]
    public function live_cashbox_figures_match_the_cashbox_page_and_pending_deposits_and_intakes_add_nothing(): void
    {
        $sessions = $this->recordScenario();

        $live = collect($this->report()->viewData('cashbox')['live'])->keyBy(fn (array $row): string => $row['store']->code);

        $this->assertSame(['open', 79_700, 60_700], [$live['KT01']['state'], $live['KT01']['expected'], $live['KT01']['available']]);
        $this->assertSame([
            'expense_payouts' => ['count' => 1, 'amount' => 3_000],
            'supplier_payouts' => ['count' => 1, 'amount' => 6_000],
            'withdrawals' => ['count' => 1, 'amount' => 10_000],
            'deposits' => ['count' => 1, 'amount' => 50_000],
            'intakes' => ['count' => 1, 'amount' => 3_000],
        ], $live['KT01']['pending']);
        $this->assertSame(['closed', $sessions['d']->id], [$live['KT02']['state'], $live['KT02']['previous']->id]);
        $this->assertSame('untracked', $live['KT03']['state']);

        $page = Livewire::actingAs($this->owner)->test(CashboxPage::class, ['store' => $this->store]);
        $this->assertSame([79_700, 60_700], [(int) $page->get('expectedShown'), $page->viewData('available')]);

        $this->report()->assertSee('797,00 TRY')->assertSee('607,00 TRY')->assertSee('Cash tracking has not started in this branch.');
    }

    #[Test]
    public function supplier_balances_come_from_the_ledger_per_currency_with_reversals_and_the_invoice_cross_check(): void
    {
        $this->recordScenario();

        $suppliers = $this->report()->viewData('suppliers');

        $this->assertSame([
            'TRY' => ['balance' => 220_000, 'open_invoices' => 220_000, 'stores' => [$this->store->id => ['balance' => 220_000, 'open_invoices' => 220_000]]],
            'USD' => ['balance' => 3_000, 'open_invoices' => 3_000, 'stores' => [$this->second->id => ['balance' => 3_000, 'open_invoices' => 3_000]]],
        ], $suppliers['totals']);
        $this->assertSame([$this->store->id => ['count' => 1, 'amount' => 6_000]], $suppliers['pending']);
        $this->assertSame(
            [['Anka Toptan', 'TRY', 220_000, 220_000], ['Anka Toptan', 'USD', 3_000, 3_000]],
            $suppliers['suppliers']->getCollection()->map(fn (array $row): array => [$row['name'], $row['currency'], $row['balance'], $row['open_invoices']])->all(),
        );

        $anka = OperationalSupplier::query()->where('name', 'Anka Toptan')->sole();
        $this->report()
            ->assertSee('2.200,00 TRY')
            ->assertSee('30,00 USD')
            ->assertDontSee('Bogaz Elektronik')
            ->assertDontSee(__('purchases.account.not_reconciled'))
            ->assertSee(route('subdomain.suppliers.account', [$anka, 'currency' => 'USD']));

        DB::table('purchases')->where('currency', 'USD')->update(['paid_total' => '25.00']);

        $mismatched = $this->report();
        $this->assertSame([3_000, 2_500], [$mismatched->viewData('suppliers')['totals']['USD']['balance'], $mismatched->viewData('suppliers')['totals']['USD']['open_invoices']]);
        $mismatched->assertSee(__('purchases.account.not_reconciled'));
    }

    #[Test]
    public function customer_debt_counts_each_debt_once_despite_several_allocations_and_ignores_pending_cash(): void
    {
        $this->recordScenario();

        $debts = $this->report()->viewData('debts');

        $this->assertSame(
            ['KT01' => [7_970, 1, ['count' => 1, 'amount' => 3_000]], 'KT02' => [0, 0, ['count' => 0, 'amount' => 0]], 'KT03' => [0, 0, ['count' => 0, 'amount' => 0]]],
            collect($debts['rows'])->mapWithKeys(fn (array $row): array => [$row['store']->code => [$row['due'], $row['debtors'], $row['pending']]])->all(),
        );
        $this->assertSame([7_970, 1], [$debts['due'], $debts['debtors']]);
        $this->report()->assertSee('79,70 TRY')->assertSee(route('pos.customers', $this->store));
    }

    #[Test]
    public function aggregates_above_the_single_operation_cap_stay_exact_and_signed_sums_refuse_overflow(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-10 09:00:00', 'UTC'));
        $first = $this->expense($this->store, null, 'card', 'rent', '10', '2026-11-10')->expense;
        $second = $this->expense($this->second, null, 'card', 'rent', '10', '2026-11-10')->expense;
        DB::table('expenses')->where('id', $first->id)->update(['amount' => '9000000000.00']);
        DB::table('expenses')->where('id', $second->id)->update(['amount' => '2000000000.50']);

        $report = $this->report();

        $this->assertGreaterThan(Money::MAX_TOTAL_AMOUNT, $report->viewData('expenses')['total']);
        $this->assertSame(1_100_000_000_050, $report->viewData('expenses')['total']);
        $report->assertSee('11.000.000.000,50 TRY');
        $this->assertSame(-5, Money::sumSigned([PHP_INT_MAX, -PHP_INT_MAX, -5]));

        foreach ([[PHP_INT_MAX, 1], [PHP_INT_MIN, -1]] as $amounts) {
            try {
                Money::sumSigned($amounts);
                $this->fail('Expected an overflow to be refused.');
            } catch (InvalidAmountException $exception) {
                $this->assertSame(InvalidAmountException::TOO_LARGE, $exception->reason);
            }
        }
    }

    #[Test]
    public function each_section_follows_its_own_permission_and_a_supplier_only_owner_still_gets_in(): void
    {
        $this->recordScenario();

        $sections = fn (): array => array_keys(array_filter([
            'expenses' => $this->report()->viewData('expenses'),
            'cashbox' => $this->report()->viewData('cashbox'),
            'suppliers' => $this->report()->viewData('suppliers'),
            'debts' => $this->report()->viewData('debts'),
        ]));

        $this->assertSame(['expenses', 'cashbox', 'suppliers', 'debts'], $sections());

        $this->revokeFromOwners('manage_expenses');
        $this->assertSame(['cashbox', 'suppliers', 'debts'], $sections());
        $this->report()->assertDontSee('Office paper')->assertDontSee('1.200,00 TRY');

        $this->revokeFromOwners('manage_cashbox');
        $this->assertSame(['suppliers', 'debts'], $sections());
        $this->report()->assertDontSee('797,00 TRY');

        $this->revokeFromOwners('receive_customer_payments');
        $this->assertSame(['suppliers'], $sections());

        $this->revokeFromOwners('use_pos');
        $this->actingAs($this->owner->fresh())->get(route('subdomain.operations.finance'))
            ->assertOk()
            ->assertSee('2.200,00 TRY')
            ->assertDontSee(route('subdomain.operations.dashboard', self::DAY));

        $this->revokeFromOwners('manage_purchases');
        $this->assertSame(['suppliers'], $sections());
        $this->revokeFromOwners('pay_suppliers');
        $this->actingAs($this->owner->fresh())->get(route('subdomain.operations.finance'))->assertForbidden();
    }

    #[Test]
    public function only_owners_reach_the_report_and_branch_drill_downs_stay_in_their_branch(): void
    {
        $sessions = $this->recordScenario();
        $cashier = $this->createCashier();

        $this->actingAs($this->owner)->get(route('subdomain.operations.finance', self::DAY))->assertOk()->assertSee('Finance overview');
        $this->actingAs($cashier)->get(route('subdomain.operations.finance'))->assertRedirect();
        Livewire::actingAs($cashier)->test(FinanceOverview::class)->assertForbidden();

        $salesperson = User::factory()->create()->assignRole('salesperson');
        $this->actingAs($salesperson)->get(route('subdomain.operations.finance'))->assertForbidden();

        $this->actingAs($this->owner)->get(route('pos.cashbox.session', [$this->second, $sessions['b']]))->assertOk()->assertSee('+25,00');

        $this->withoutExceptionHandling();

        try {
            Livewire::actingAs($this->owner)->test(CashboxSessionPage::class, ['store' => $this->store, 'session' => $sessions['b']->id]);
            $this->fail('A session of another branch must not open under this branch.');
        } catch (ModelNotFoundException) {
            $this->addToAssertionCount(1);
        }

        Livewire::actingAs($this->owner)->withQueryParams(self::DAY)->test(OperationsDashboard::class)
            ->assertSee(route('subdomain.operations.finance', self::DAY));
        $this->report()->assertSee(route('subdomain.operations.dashboard', self::DAY));
    }

    #[Test]
    public function an_invalid_period_suppresses_period_figures_but_keeps_current_sections(): void
    {
        $this->recordScenario();

        $report = Livewire::actingAs($this->owner)->withQueryParams(['from' => '2026-11-11', 'to' => '2026-11-10'])->test(FinanceOverview::class)
            ->assertViewHas('rangeError', 'reversed')
            ->assertSee('From must be on or before To.')
            ->assertSee('Fix the period above to see these figures.')
            ->assertSee('797,00 TRY')
            ->assertSee('2.200,00 TRY')
            ->assertSee('79,70 TRY');

        $this->assertSame([[], null, 0], [$report->viewData('expenses')['categories'], $report->viewData('cashbox')['differences'], $report->viewData('cashbox')['sessions']->total()]);

        $this->travelTo(CarbonImmutable::parse('2026-11-10 22:30:00', 'UTC'));
        Livewire::actingAs($this->owner)->withQueryParams([])->test(FinanceOverview::class)->assertSet('from', '2026-11-11')->assertSet('to', '2026-11-11');
    }

    private function report(): Testable
    {
        return Livewire::actingAs($this->owner->fresh())->withQueryParams(self::DAY)->test(FinanceOverview::class);
    }

    /**
     * KT01 on 2026-11-10: a session closed 3,00 short, then an open session with a confirmed
     * 200,00 cash expense, a pending 30,00 expense payout, a released 40,00 payout, a pending
     * 100,00 withdrawal and 500,00 deposit, a pending 30,00 debt cash intake, and a pending 60,00
     * supplier payout. A card expense of 50,00 dated the day before. KT02: sessions closed just
     * before, inside, at the end of, and just after the Istanbul day, and a 1.000,00 card rent.
     * Suppliers: KT01 owes 2.200,00 TRY after a reversed payment, KT02 owes 30,00 USD, and a
     * reversed invoice owes nothing. Customers: one debt with two allocations and 79,70 due.
     *
     * @return array{first: CashboxSession, a: CashboxSession, b: CashboxSession, c: CashboxSession, d: CashboxSession}
     */
    private function recordScenario(): array
    {
        $at = fn (string $utc) => $this->travelTo(CarbonImmutable::parse($utc, 'UTC'));

        $at('2026-11-09 18:00:00');
        $a = $this->open($this->second, '1000');
        $at('2026-11-09 20:59:59');
        $this->close($this->second, $a, '1000', '990');
        $at('2026-11-09 21:00:00');
        $b = $this->open($this->second, '995', 'Found 5 in the drawer');
        $at('2026-11-10 10:00:00');
        $this->close($this->second, $b, '995', '1020');
        $at('2026-11-10 11:00:00');
        $c = $this->open($this->second, '1020');
        $at('2026-11-10 20:59:59');
        $this->close($this->second, $c, '1020', '1000');
        $d = $this->open($this->second, '1000');
        $at('2026-11-10 21:00:00');
        $this->close($this->second, $d, '1000', '1001');

        $at('2026-11-10 06:00:00');
        $anka = OperationalSupplier::factory()->create(['name' => 'Anka Toptan']);
        $bogaz = OperationalSupplier::factory()->create(['name' => 'Bogaz Elektronik']);
        $try = $this->postedPurchase($this->owner, $this->store, $anka, [[$this->cable, 100, '25.00']], ['supplier_invoice_number' => 'TRY-1']);
        $reversed = app(RecordSupplierPayment::class)($this->owner, $try, $this->paymentInput('500', 'EFT-500'), 'eft-500');
        app(ReverseSupplierPayment::class)($this->owner, $try->refresh(), $reversed->id, 'Bank returned the transfer');
        app(RecordSupplierPayment::class)($this->owner, $try->refresh(), $this->paymentInput('300', 'EFT-300'), 'eft-300');
        $usd = $this->postedPurchase($this->owner, $this->second, $anka, [[$this->cable, 10, '5.00']], ['supplier_invoice_number' => 'USD-1'], Currency::USD);
        app(RecordSupplierPayment::class)($this->owner, $usd, $this->paymentInput('20', 'USD-20'), 'usd-20');
        $cancelled = $this->postedPurchase($this->owner, $this->store, $bogaz, [[$this->cable, 2, '10.00']], ['supplier_invoice_number' => 'BGZ-1']);
        app(ReversePurchase::class)($this->owner, $cancelled, 'Entered twice');

        $debtor = Customer::factory()->atCounter($this->store, '05321234567')->create(['name' => 'Ahmet Yılmaz']);
        $settled = Customer::factory()->atCounter($this->store, '05329876543')->create(['name' => 'Mehmet Kaya']);
        $this->creditSale($debtor, 3, '149,70');
        $this->creditSale($settled, 2, '99,80');
        $this->customerPayment($debtor, '50', 'TR-50');
        $this->customerPayment($debtor, '20', 'TR-20');
        $this->customerPayment($settled, '99.80', 'TR-9980');

        $at('2026-11-10 07:00:00');
        $first = $this->open($this->store, '1000');
        $this->close($this->store, $first, '1000', '997');
        $at('2026-11-10 07:30:00');
        $open = $this->open($this->store, '997');

        $paper = $this->expense($this->store, $open->id, 'cash', 'supplies', '200', '2026-11-10', 'Office paper')->payout;
        app(ConfirmCashPayout::class)($this->owner, $this->store, $paper->id, $open->id);
        $this->expense($this->store, null, 'card', 'utilities', '50', '2026-11-09', 'Electricity');
        $this->expense($this->store, $open->id, 'cash', 'transport', '30', '2026-11-10', 'Courier');
        $released = $this->expense($this->store, $open->id, 'cash', 'maintenance', '40', '2026-11-10', 'Locksmith')->payout;
        app(ReleaseCashPayout::class)($this->owner, $this->store, $released->id, 'not_handed_out', 'Locksmith did not come');
        $this->transfer($open, 'withdrawal', '100');
        $this->transfer($open, 'deposit', '500');
        app(StartCustomerCashIntake::class)($this->owner, $this->store, $debtor, '30', (string) Str::uuid());
        app(RecordSupplierPayment::class)($this->owner, $try->refresh(), $this->paymentInput('60', 'CASH-60', ['method' => 'cash']), 'cash-60', $open->id);

        $at('2026-11-10 12:00:00');
        $this->expense($this->second, null, 'card', 'rent', '1000', '2026-11-10', 'November rent');
        $this->travelBack();

        $this->assertSame([220_000, PurchaseStatus::REVERSED], [$try->refresh()->outstandingMinor(), $cancelled->refresh()->status]);
        $this->assertSame(1, Expense::query()->where('store_id', $this->store->id)->where('expense_date', '2026-11-10')->count());

        return [
            'first' => $first->refresh(),
            'a' => $a->refresh(),
            'b' => $b->refresh(),
            'c' => $c->refresh(),
            'd' => $d->refresh(),
        ];
    }

    private function open(Store $store, string $counted, ?string $note = null): CashboxSession
    {
        return app(OpenCashboxSession::class)($this->owner, $store, $counted, $note);
    }

    private function close(Store $store, CashboxSession $session, string $expected, string $counted): void
    {
        app(CloseCashboxSession::class)($this->owner, $store, $session->id, $expected, $counted, $expected === $counted ? null : 'Count checked twice');
    }

    private function expense(Store $store, ?int $sessionId, string $method, string $category, string $amount, string $date, string $description = 'Expense'): ExpenseSubmission
    {
        return app(SubmitExpense::class)($this->owner, $store, $sessionId, [
            'method' => $method,
            'category' => $category,
            'description' => $description,
            'amount' => $amount,
            'expense_date' => $date,
            'reference' => $method === 'cash' ? null : 'REF-'.Str::upper(Str::random(6)),
        ], (string) Str::uuid());
    }

    private function transfer(CashboxSession $session, string $kind, string $amount): void
    {
        app(StartCashboxTransfer::class)($this->owner, $this->store, $session->id, ['kind' => $kind, 'amount' => $amount, 'reason' => 'Bank run'], (string) Str::uuid());
    }

    private function customerPayment(Customer $customer, string $amount, string $reference): void
    {
        app(RecordCustomerPayment::class)($this->owner, $this->store, $customer, ['amount' => $amount, 'method' => 'bank_transfer', 'reference' => $reference], (string) Str::uuid());
    }
}
