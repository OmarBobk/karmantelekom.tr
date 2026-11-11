<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Inventory\RecordOpeningBalance;
use App\Exceptions\Purchasing\InvalidAmountException;
use App\Livewire\Backend\Reports\OperationsDashboard;
use App\Livewire\Pos\SalesHistory;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use App\Services\Purchasing\Money;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\InteractsWithPos;
use Tests\TestCase;

class OperationsDashboardTest extends TestCase
{
    use InteractsWithPos;
    use RefreshDatabase;

    private Store $second;

    private Store $third;

    private User $firstCashier;

    private User $secondCashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCounter(100);
        $this->useEnglishLocale();
        $this->second = Store::factory()->create(['code' => 'KT02', 'name' => 'Karman Aksaray']);
        $this->third = Store::factory()->create(['code' => 'KT03', 'name' => 'Karman Empty']);
        app(RecordOpeningBalance::class)($this->owner, $this->second, $this->cable, 50, null, (string) Str::uuid());
        $this->firstCashier = $this->createCashier(name: 'First');
        $this->secondCashier = $this->createCashier($this->second, name: 'Second');
    }

    #[Test]
    public function branch_and_owner_totals_reconcile_on_istanbul_days_including_full_credit_sales_and_empty_branches(): void
    {
        $sales = $this->sellAroundIstanbulDay();

        $dashboard = Livewire::actingAs($this->owner)
            ->withQueryParams(['from' => '2026-11-10', 'to' => '2026-11-10'])
            ->test(OperationsDashboard::class);

        $rows = $dashboard->viewData('rows')->mapWithKeys(fn (array $row): array => [$row['store']->code => [$row['count'], $row['total_minor']]])->all();
        $this->assertSame(['KT01' => [2, 24_950], 'KT02' => [1, 19_960], 'KT03' => [0, 0]], $rows);
        $this->assertSame([3, 44_910], [$dashboard->viewData('ownerCount'), $dashboard->viewData('ownerTotalMinor')]);

        $dashboard
            ->assertSee('249,50 TRY')
            ->assertSee('199,60 TRY')
            ->assertSee('449,10 TRY')
            ->assertSee(route('pos.sales', [$this->store, 'from' => '2026-11-10', 'to' => '2026-11-10']))
            ->assertSee(route('pos.sales', [$this->third, 'from' => '2026-11-10', 'to' => '2026-11-10']));

        $history = Livewire::actingAs($this->owner)
            ->withQueryParams(['from' => '2026-11-10', 'to' => '2026-11-10'])
            ->test(SalesHistory::class, ['store' => $this->store]);

        $this->assertSame(['count' => 2, 'total_minor' => 24_950], $history->viewData('summary'));
        $history
            ->assertSee($sales['midnight']->number)
            ->assertSee($sales['credit']->number)
            ->assertDontSee($sales['before']->number)
            ->assertDontSee($sales['after']->number);

        $history->set('search', $sales['credit']->number);
        $this->assertSame(['count' => 1, 'total_minor' => 14_970], $history->viewData('summary'));

        $previousDay = Livewire::actingAs($this->owner)
            ->withQueryParams(['from' => '2026-11-09', 'to' => '2026-11-09'])
            ->test(OperationsDashboard::class);
        $this->assertSame([1, 4_990], [$previousDay->viewData('ownerCount'), $previousDay->viewData('ownerTotalMinor')]);
    }

    #[Test]
    public function the_dashboard_defaults_to_today_in_istanbul_and_the_history_stays_unfiltered(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-10 22:30:00', 'UTC'));

        Livewire::actingAs($this->owner)->test(OperationsDashboard::class)
            ->assertSet('from', '2026-11-11')
            ->assertSet('to', '2026-11-11');

        Livewire::actingAs($this->owner)->test(SalesHistory::class, ['store' => $this->store])
            ->assertSet('from', '')
            ->assertSet('to', '')
            ->assertViewHas('rangeError', null);
    }

    #[Test]
    public function report_totals_above_the_single_operation_cap_stay_exact(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-10 09:00:00', 'UTC'));
        $first = $this->finalize($this->firstCashier, $this->review($this->firstCashier, $this->cableLine(1)), '50');
        $second = $this->finalize($this->secondCashier, $this->review($this->secondCashier, $this->cableLine(1), null, $this->second), '50');
        DB::table('sales')->where('id', $first->id)->update(['subtotal' => '9000000000.00', 'discount_total' => '0.00', 'total' => '9000000000.00']);
        DB::table('sales')->where('id', $second->id)->update(['subtotal' => '2000000000.50', 'discount_total' => '0.00', 'total' => '2000000000.50']);

        $dashboard = Livewire::actingAs($this->owner)
            ->withQueryParams(['from' => '2026-11-10', 'to' => '2026-11-10'])
            ->test(OperationsDashboard::class);

        $this->assertGreaterThan(Money::MAX_TOTAL_AMOUNT, $dashboard->viewData('ownerTotalMinor'));
        $this->assertSame(1_100_000_000_050, $dashboard->viewData('ownerTotalMinor'));
        $dashboard->assertSee('11.000.000.000,50 TRY');

        $this->expectException(InvalidAmountException::class);
        Money::sumUncapped([PHP_INT_MAX, 1]);
    }

    #[Test]
    public function legacy_date_links_still_work_and_explicit_bounds_take_precedence(): void
    {
        $this->sellAroundIstanbulDay();

        $legacy = Livewire::actingAs($this->owner)
            ->withQueryParams(['date' => '2026-11-10'])
            ->test(SalesHistory::class, ['store' => $this->store])
            ->assertSet('from', '2026-11-10')
            ->assertSet('to', '2026-11-10')
            ->assertSet('date', '');
        $this->assertSame(2, $legacy->viewData('summary')['count']);

        $explicit = Livewire::actingAs($this->owner)
            ->withQueryParams(['date' => '2026-11-10', 'from' => '2026-11-09'])
            ->test(SalesHistory::class, ['store' => $this->store])
            ->assertSet('from', '2026-11-09')
            ->assertSet('to', '2026-11-09');
        $this->assertSame(['count' => 1, 'total_minor' => 4_990], $explicit->viewData('summary'));

        $this->actingAs($this->owner)->get(route('pos.sales', [$this->store, 'date' => '2026-11-10']))->assertOk();
    }

    #[Test]
    public function invalid_dates_and_reversed_ranges_show_an_error_and_run_no_report(): void
    {
        $this->sellAroundIstanbulDay();

        Livewire::actingAs($this->owner)
            ->withQueryParams(['from' => '2026-02-30', 'to' => '2026-03-01'])
            ->test(SalesHistory::class, ['store' => $this->store])
            ->assertViewHas('rangeError', 'invalid_date')
            ->assertViewHas('summary', null)
            ->assertSee('Enter real dates.');

        Livewire::actingAs($this->owner)
            ->test(SalesHistory::class, ['store' => $this->store])
            ->set('from', '2026-11-11')
            ->set('to', '2026-11-10')
            ->assertViewHas('rangeError', 'reversed')
            ->assertSee('From must be on or before To.');

        Livewire::actingAs($this->owner)->test(OperationsDashboard::class)
            ->set('from', 'not-a-date')
            ->assertViewHas('rangeError', 'invalid_date')
            ->assertDontSee('All branches')
            ->set('from', '2026-11-11')
            ->set('to', '2026-11-10')
            ->assertViewHas('rangeError', 'reversed')
            ->assertSee('From must be on or before To.');

        $this->actingAs($this->owner)->get(route('subdomain.operations.dashboard', ['from' => '2026-13-01', 'to' => '2026-13-02']))
            ->assertOk()
            ->assertSee('Enter real dates for both From and To.');
    }

    #[Test]
    public function changing_a_history_filter_returns_to_the_first_page(): void
    {
        Livewire::actingAs($this->owner)->test(SalesHistory::class, ['store' => $this->store])
            ->call('setPage', 2)
            ->assertSet('paginators.page', 2)
            ->set('from', '2026-11-10')
            ->assertSet('paginators.page', 1)
            ->call('setPage', 3)
            ->set('search', 'KT01')
            ->assertSet('paginators.page', 1);
    }

    #[Test]
    public function only_the_owner_reaches_the_dashboard_and_a_branch_view_shows_only_that_branch(): void
    {
        $sales = $this->sellAroundIstanbulDay();
        $range = ['from' => '2026-11-10', 'to' => '2026-11-10'];

        $this->actingAs($this->owner)->get(route('subdomain.operations.dashboard', $range))->assertOk()->assertSee('Recorded sales');
        $this->actingAs($this->owner)->get(route('subdomain.operations.dashboard.store', [$this->second, ...$range]))->assertOk();

        $this->actingAs($this->firstCashier)->get(route('subdomain.operations.dashboard'))->assertRedirect();
        Livewire::actingAs($this->firstCashier)->test(OperationsDashboard::class)->assertForbidden();
        Livewire::actingAs($this->firstCashier)->test(OperationsDashboard::class, ['store' => $this->store])->assertForbidden();

        $salesperson = User::factory()->create()->assignRole('salesperson');
        $this->actingAs($salesperson)->get(route('subdomain.operations.dashboard'))->assertForbidden();
        $this->actingAs($salesperson)->get(route('subdomain.operations.dashboard.store', $this->store))->assertForbidden();

        $branch = Livewire::actingAs($this->owner)->withQueryParams($range)->test(OperationsDashboard::class, ['store' => $this->second]);
        $this->assertSame(['KT02'], $branch->viewData('rows')->map(fn (array $row): string => $row['store']->code)->all());
        $this->assertSame(['KT02'], $branch->viewData('inventory')->pluck('code')->all());
        $branch->assertSee('199,60 TRY')->assertDontSee('249,50 TRY')->assertDontSee($sales['credit']->number);

        $this->expectException(CannotUpdateLockedPropertyException::class);
        $branch->set('storeId', $this->store->id);
    }

    #[Test]
    public function inventory_is_current_and_gated_separately_from_sales(): void
    {
        $this->sellAroundIstanbulDay();

        $dashboard = Livewire::actingAs($this->owner)->withQueryParams(['from' => '2026-11-10', 'to' => '2026-11-10'])->test(OperationsDashboard::class);
        $inventory = $dashboard->viewData('inventory')->mapWithKeys(fn (Store $store): array => [$store->code => [(int) $store->quantity_on_hand, $store->available_units_count]])->all();
        $this->assertSame(['KT01' => [93, 1], 'KT02' => [46, 0], 'KT03' => [0, 0]], $inventory);
        $dashboard->assertSee('Current inventory')->assertSee(route('subdomain.inventory.store', $this->third));

        $this->revokeFromOwners('view_inventory');

        Livewire::actingAs($this->owner->fresh())->withQueryParams(['from' => '2026-11-10', 'to' => '2026-11-10'])->test(OperationsDashboard::class)
            ->assertViewHas('ownerTotalMinor', 44_910)
            ->assertDontSee('Current inventory');

        $this->revokeFromOwners('use_pos');

        $this->actingAs($this->owner->fresh())->get(route('subdomain.operations.dashboard'))->assertForbidden();
    }

    #[Test]
    public function employees_keep_seeing_only_their_own_sales_without_a_branch_summary(): void
    {
        $sales = $this->sellAroundIstanbulDay();

        Livewire::actingAs($this->firstCashier)
            ->withQueryParams(['from' => '2026-11-10', 'to' => '2026-11-10'])
            ->test(SalesHistory::class, ['store' => $this->store])
            ->assertViewHas('summary', null)
            ->assertSee($sales['midnight']->number)
            ->assertDontSee($sales['credit']->number);
    }

    /**
     * KT01: one sale at each edge of 2026-11-10 in Istanbul (UTC+3), a fully credited owner sale
     * inside it, and one sale on each neighbouring day. KT02: one sale inside it. KT03: none.
     *
     * @return array{before: Sale, midnight: Sale, credit: Sale, after: Sale, second: Sale}
     */
    private function sellAroundIstanbulDay(): array
    {
        $customer = Customer::factory()->atCounter($this->store, '05321234567')->create(['name' => 'Ahmet Yılmaz']);
        $at = fn (string $utc) => $this->travelTo(CarbonImmutable::parse($utc, 'UTC'));

        $at('2026-11-09 20:59:59');
        $before = $this->finalize($this->firstCashier, $this->review($this->firstCashier, $this->cableLine(1)), '50');
        $at('2026-11-09 21:00:00');
        $midnight = $this->finalize($this->firstCashier, $this->review($this->firstCashier, $this->cableLine(2)), '100');
        $at('2026-11-10 20:59:59');
        $credit = $this->creditSale($customer, 3, '149,70');
        $at('2026-11-10 12:00:00');
        $second = $this->finalize($this->secondCashier, $this->review($this->secondCashier, $this->cableLine(4), null, $this->second), '200');
        $at('2026-11-10 21:00:00');
        $after = $this->finalize($this->firstCashier, $this->review($this->firstCashier, $this->cableLine(1)), '50');
        $this->travelBack();

        $this->assertSame(['149.70', 14_970], [$credit->total, $credit->creditMinor()]);

        return compact('before', 'midnight', 'credit', 'after', 'second');
    }
}
