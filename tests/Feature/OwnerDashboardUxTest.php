<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Cashbox\OpenCashboxSession;
use App\Actions\Customers\RecordCustomerPayment;
use App\Actions\Inventory\RecordOpeningBalance;
use App\Actions\Pos\ReviewCheckout;
use App\Actions\Purchasing\RecordSupplierPayment;
use App\Livewire\Backend\Reports\AuditLog;
use App\Livewire\Backend\Reports\FinanceOverview;
use App\Livewire\Backend\Reports\OperationalReview;
use App\Livewire\Backend\Reports\OperationsDashboard;
use App\Livewire\Pos\SalesHistory;
use App\Models\Customer;
use App\Models\OperationalSupplier;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use App\Services\Purchasing\Money;
use App\Services\Reporting\LocalDateRange;
use App\Services\Reporting\RecordedSalesReport;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\InteractsWithPos;
use Tests\Support\InteractsWithPurchasing;
use Tests\TestCase;

class OwnerDashboardUxTest extends TestCase
{
    use InteractsWithPos;
    use InteractsWithPurchasing;
    use RefreshDatabase;

    private Store $second;

    private User $firstCashier;

    private User $secondCashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCounter(100);
        $this->useEnglishLocale();
        $this->second = Store::factory()->create(['code' => 'KT02', 'name' => 'Karman Aksaray']);
        app(RecordOpeningBalance::class)($this->owner, $this->second, $this->cable, 50, null, (string) Str::uuid());
        $this->firstCashier = $this->createCashier(name: 'First');
        $this->secondCashier = $this->createCashier($this->second, name: 'Second');
    }

    #[Test]
    public function previous_equal_period_boundaries_and_empty_previous_period_are_exact(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-10 09:00:00', 'UTC'));
        $today = $this->finalize($this->firstCashier, $this->review($this->firstCashier, $this->cableLine(2)), '100');
        $this->travelTo(CarbonImmutable::parse('2026-11-09 09:00:00', 'UTC'));
        $yesterday = $this->finalize($this->firstCashier, $this->review($this->firstCashier, $this->cableLine(1)), '50');
        $this->travelBack();

        $from = LocalDateRange::date('2026-11-10');
        $to = LocalDateRange::date('2026-11-10');
        [$previousFrom, $previousTo] = LocalDateRange::previousEqual($from, $to);
        $this->assertSame(['2026-11-09', '2026-11-09'], [$previousFrom->toDateString(), $previousTo->toDateString()]);

        $weekFrom = LocalDateRange::date('2026-11-04');
        $weekTo = LocalDateRange::date('2026-11-10');
        [$weekPreviousFrom, $weekPreviousTo] = LocalDateRange::previousEqual($weekFrom, $weekTo);
        $this->assertSame(['2026-10-28', '2026-11-03'], [$weekPreviousFrom->toDateString(), $weekPreviousTo->toDateString()]);

        $dashboard = Livewire::actingAs($this->owner)
            ->withQueryParams(['from' => '2026-11-10', 'to' => '2026-11-10'])
            ->test(OperationsDashboard::class);

        $this->assertSame('2026-11-09', $dashboard->viewData('previousFrom'));
        $this->assertSame('2026-11-09', $dashboard->viewData('previousTo'));
        $this->assertSame([$today->totalMinor()], [$dashboard->viewData('ownerTotalMinor')]);
        $this->assertSame([$yesterday->totalMinor()], [$dashboard->viewData('ownerPreviousTotalMinor')]);
        $this->assertSame($today->totalMinor() - $yesterday->totalMinor(), $dashboard->viewData('ownerTotalDelta'));

        $emptyPrevious = Livewire::actingAs($this->owner)
            ->withQueryParams(['from' => '2026-11-08', 'to' => '2026-11-08'])
            ->test(OperationsDashboard::class);
        $this->assertSame([0, 0, 0], [
            $emptyPrevious->viewData('ownerPreviousCount'),
            $emptyPrevious->viewData('ownerPreviousTotalMinor'),
            $emptyPrevious->viewData('ownerCountDelta'),
        ]);
        $emptyPrevious->assertDontSee('n/a')->assertDontSee('∞')->assertSee('Previous:');
    }

    #[Test]
    public function cash_card_and_original_credit_reconcile_and_ignore_later_collections(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-10 09:00:00', 'UTC'));
        $customer = Customer::factory()->atCounter($this->store, '05321112233')->create(['name' => 'Ayşe']);
        $cash = $this->finalize($this->firstCashier, $this->review($this->firstCashier, $this->cableLine(1)), '50');
        $credit = $this->creditSale($customer, 2, '99,80');
        $terminal = $this->createTerminal();
        $cardAttempt = $this->review($this->firstCashier, $this->cableLine(1));
        $this->approveCard($this->firstCashier, $cardAttempt, $terminal, Money::toDecimal($cardAttempt->fresh()->payableMinor()));
        $card = $this->finalize($this->firstCashier, $cardAttempt->fresh());

        $mixedAttempt = app(ReviewCheckout::class)($this->owner, $this->store, $this->cableLine(2), null, null, $customer->id, '40,00');
        $this->approveCard($this->owner, $mixedAttempt, $terminal, '59.80', 'MIXED1');
        $mixed = $this->finalize($this->owner, $mixedAttempt->fresh());

        $composition = RecordedSalesReport::compositionByStore(
            [$this->store->id],
            LocalDateRange::date('2026-11-10'),
            LocalDateRange::date('2026-11-10'),
        )[$this->store->id];

        $this->assertSame(
            $composition['total'],
            $composition['cash'] + $composition['card'] + $composition['credit'],
        );
        $this->assertSame(
            $cash->totalMinor() + $credit->totalMinor() + $card->totalMinor() + $mixed->totalMinor(),
            $composition['total'],
        );
        $this->assertSame($credit->creditMinor() + $mixed->creditMinor(), $composition['credit']);
        $this->assertGreaterThan(0, $composition['cash']);
        $this->assertGreaterThan(0, $composition['card']);

        app(RecordCustomerPayment::class)($this->owner, $this->store, $customer, [
            'amount' => Money::toDecimal($credit->creditMinor()),
            'method' => 'bank_transfer',
            'reference' => 'COLLECT-1',
        ], (string) Str::uuid());

        $afterCollection = RecordedSalesReport::compositionByStore(
            [$this->store->id],
            LocalDateRange::date('2026-11-10'),
            LocalDateRange::date('2026-11-10'),
        )[$this->store->id];
        $this->assertSame($composition, $afterCollection);

        $history = Livewire::actingAs($this->owner)
            ->withQueryParams(['from' => '2026-11-10', 'to' => '2026-11-10', 'tender' => 'credit'])
            ->test(SalesHistory::class, ['store' => $this->store]);

        $summary = $history->viewData('summary');
        $this->assertSame($composition['credit'], $summary['match_minor']);
        $this->assertSame($credit->totalMinor() + $mixed->totalMinor(), $summary['total_minor']);
        $history->assertSee('Matching Original credit')->assertSee('tender=credit');

        $csv = $this->actingAs($this->owner)
            ->get(route('pos.sales.export', [$this->store, 'from' => '2026-11-10', 'to' => '2026-11-10', 'tender' => 'credit']));
        $csv->assertOk();
        $body = $csv->streamedContent();
        $this->assertStringContainsString($credit->number, $body);
        $this->assertStringContainsString($mixed->number, $body);
        $this->assertStringNotContainsString($cash->number, $body);
    }

    #[Test]
    public function chart_buckets_and_branch_totals_reconcile_without_inventing_history(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-09 12:00:00', 'UTC'));
        $first = $this->finalize($this->firstCashier, $this->review($this->firstCashier, $this->cableLine(1)), '50');
        $this->travelTo(CarbonImmutable::parse('2026-11-10 12:00:00', 'UTC'));
        $second = $this->finalize($this->secondCashier, $this->review($this->secondCashier, $this->cableLine(1), null, $this->second), '50');
        $this->travelBack();

        $series = RecordedSalesReport::series(
            [$this->store->id, $this->second->id],
            LocalDateRange::date('2026-11-09'),
            LocalDateRange::date('2026-11-10'),
        );

        $this->assertSame(LocalDateRange::GRANULARITY_DAY, $series['granularity']);
        $this->assertCount(2, $series['buckets']);
        $this->assertSame(2, array_sum(array_column($series['buckets'], 'count')));
        $this->assertSame(
            $first->totalMinor() + $second->totalMinor(),
            Money::sumUncapped(array_column($series['buckets'], 'total_minor')),
        );

        $singleDay = RecordedSalesReport::series(
            [$this->store->id],
            LocalDateRange::date('2026-11-09'),
            LocalDateRange::date('2026-11-09'),
        );
        $this->assertSame(LocalDateRange::GRANULARITY_HOUR, $singleDay['granularity']);
        $this->assertCount(24, $singleDay['buckets']);
        $this->assertSame(1, array_sum(array_column($singleDay['buckets'], 'count')));
        $this->assertSame(23, collect($singleDay['buckets'])->where('count', 0)->count());

        $dashboard = Livewire::actingAs($this->owner)
            ->withQueryParams(['from' => '2026-11-09', 'to' => '2026-11-10'])
            ->test(OperationsDashboard::class);

        $payload = $dashboard->viewData('chartPayload');
        $this->assertSame(
            Money::sumUncapped(array_map(fn ($total): int => (int) round($total * 100), $payload['branches']['totals'])),
            $dashboard->viewData('ownerTotalMinor'),
        );
        $this->assertFalse($payload['singleDay']);
        $dashboard->assertSee('Sales over time')->assertSee('Branches compared');
    }

    #[Test]
    public function needs_attention_respects_permissions_and_invalid_ranges_keep_current_sections(): void
    {
        $dashboard = Livewire::actingAs($this->owner)
            ->withQueryParams(['from' => '2026-11-10', 'to' => '2026-11-10'])
            ->test(OperationsDashboard::class);

        $keys = collect($dashboard->viewData('attention'))->pluck('key')->all();
        $this->assertNotEmpty(array_filter($keys, fn (string $key): bool => str_starts_with($key, 'cashbox-')));
        $dashboard->assertSee('Needs attention');

        Livewire::actingAs($this->owner)
            ->withQueryParams(['from' => '2026-11-11', 'to' => '2026-11-10'])
            ->test(OperationsDashboard::class)
            ->assertViewHas('rangeError', 'reversed')
            ->assertViewHas('chartPayload', null)
            ->assertSee('Needs attention')
            ->assertSee('Current inventory')
            ->assertDontSee('Sales over time')
            ->assertDontSee('Recorded sales overview');

        $this->revokeFromOwners('manage_cashbox');
        $withoutCashbox = Livewire::actingAs($this->owner->fresh())
            ->withQueryParams(['from' => '2026-11-10', 'to' => '2026-11-10'])
            ->test(OperationsDashboard::class);
        $this->assertSame([], array_filter(
            collect($withoutCashbox->viewData('attention'))->pluck('key')->all(),
            fn (string $key): bool => str_starts_with($key, 'cashbox-'),
        ));
    }

    #[Test]
    public function shared_presets_update_dashboard_finance_review_and_audit(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-17 12:00:00', Sale::DISPLAY_TIMEZONE)->utc());

        Livewire::actingAs($this->owner)->test(OperationsDashboard::class)
            ->call('showYesterday')
            ->assertSet('from', '2026-11-16')
            ->assertSet('to', '2026-11-16')
            ->call('showLastSevenDays')
            ->assertSet('from', '2026-11-11')
            ->assertSet('to', '2026-11-17')
            ->call('showThisMonth')
            ->assertSet('from', '2026-11-01')
            ->assertSet('to', '2026-11-17');

        Livewire::actingAs($this->owner)->test(FinanceOverview::class)
            ->call('showLastSevenDays')
            ->assertSet('from', '2026-11-11')
            ->assertSet('to', '2026-11-17');

        Livewire::actingAs($this->owner)->test(OperationalReview::class)
            ->call('showThisMonth')
            ->assertSet('from', '2026-11-01')
            ->assertSet('to', '2026-11-17');

        Livewire::actingAs($this->owner)->test(AuditLog::class)
            ->call('setPage', 2)
            ->call('showYesterday')
            ->assertSet('from', '2026-11-16')
            ->assertSet('to', '2026-11-16')
            ->assertSet('paginators.page', 1);

        $this->travelBack();
    }

    #[Test]
    public function incomplete_period_is_labeled_when_the_range_includes_today(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-17 15:00:00', Sale::DISPLAY_TIMEZONE)->utc());

        Livewire::actingAs($this->owner)->test(OperationsDashboard::class)
            ->assertViewHas('incomplete', true)
            ->assertSee('Figures are incomplete');

        Livewire::actingAs($this->owner)
            ->withQueryParams(['from' => '2026-11-10', 'to' => '2026-11-10'])
            ->test(OperationsDashboard::class)
            ->assertViewHas('incomplete', false)
            ->assertDontSee('Figures are incomplete');

        $this->travelBack();
    }

    #[Test]
    public function weekly_and_monthly_buckets_reconcile_with_boundaries_and_partial_final_week(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-01-01 10:00:00', Sale::DISPLAY_TIMEZONE)->utc());
        $weekStart = $this->finalize($this->firstCashier, $this->review($this->firstCashier, $this->cableLine(1)), '50');
        $this->travelTo(CarbonImmutable::parse('2026-01-07 10:00:00', Sale::DISPLAY_TIMEZONE)->utc());
        $weekBoundary = $this->finalize($this->firstCashier, $this->review($this->firstCashier, $this->cableLine(1)), '50');
        $this->travelTo(CarbonImmutable::parse('2026-02-15 10:00:00', Sale::DISPLAY_TIMEZONE)->utc());
        $partialWeek = $this->finalize($this->firstCashier, $this->review($this->firstCashier, $this->cableLine(2)), '100');
        $this->travelTo(CarbonImmutable::parse('2026-07-20 10:00:00', Sale::DISPLAY_TIMEZONE)->utc());
        $monthly = $this->finalize($this->firstCashier, $this->review($this->firstCashier, $this->cableLine(1)), '50');
        $this->travelBack();

        $this->assertSame(LocalDateRange::GRANULARITY_DAY, LocalDateRange::chartGranularity(
            LocalDateRange::date('2026-01-01'),
            LocalDateRange::date('2026-02-14'),
        ));
        $this->assertSame(LocalDateRange::GRANULARITY_WEEK, LocalDateRange::chartGranularity(
            LocalDateRange::date('2026-01-01'),
            LocalDateRange::date('2026-02-15'),
        ));
        $this->assertSame(LocalDateRange::GRANULARITY_MONTH, LocalDateRange::chartGranularity(
            LocalDateRange::date('2026-01-01'),
            LocalDateRange::date('2026-07-20'),
        ));

        $weekly = RecordedSalesReport::series(
            [$this->store->id],
            LocalDateRange::date('2026-01-01'),
            LocalDateRange::date('2026-02-15'),
        );
        $this->assertSame(LocalDateRange::GRANULARITY_WEEK, $weekly['granularity']);
        $this->assertSame('2026-01-01', $weekly['buckets'][0]['key']);
        $this->assertSame('2026-01-08', $weekly['buckets'][1]['key']);
        $this->assertSame(2, $weekly['buckets'][0]['count']);
        $this->assertSame(
            $weekStart->totalMinor() + $weekBoundary->totalMinor(),
            $weekly['buckets'][0]['total_minor'],
        );
        $last = $weekly['buckets'][array_key_last($weekly['buckets'])];
        $this->assertSame('2026-02-12', $last['key']);
        $this->assertSame('2026-02-15', $last['end_date']);
        $this->assertSame(1, $last['count']);
        $this->assertSame($partialWeek->totalMinor(), $last['total_minor']);
        $this->assertSame(
            $weekStart->totalMinor() + $weekBoundary->totalMinor() + $partialWeek->totalMinor(),
            Money::sumUncapped(array_column($weekly['buckets'], 'total_minor')),
        );

        $months = RecordedSalesReport::series(
            [$this->store->id],
            LocalDateRange::date('2026-01-01'),
            LocalDateRange::date('2026-07-20'),
        );
        $this->assertSame(LocalDateRange::GRANULARITY_MONTH, $months['granularity']);
        $this->assertSame('2026-01', $months['buckets'][0]['key']);
        $this->assertSame('2026-07', $months['buckets'][array_key_last($months['buckets'])]['key']);
        $this->assertSame(
            $weekStart->totalMinor() + $weekBoundary->totalMinor() + $partialWeek->totalMinor() + $monthly->totalMinor(),
            Money::sumUncapped(array_column($months['buckets'], 'total_minor')),
        );
    }

    #[Test]
    public function chart_version_changes_when_a_sale_is_added_inside_the_same_range(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-10 09:00:00', 'UTC'));
        $first = $this->finalize($this->firstCashier, $this->review($this->firstCashier, $this->cableLine(1)), '50');

        $before = Livewire::actingAs($this->owner)
            ->withQueryParams(['from' => '2026-11-10', 'to' => '2026-11-10'])
            ->test(OperationsDashboard::class);
        $versionBefore = $before->viewData('chartVersion');
        $totalBefore = $before->viewData('ownerTotalMinor');

        $this->travelTo(CarbonImmutable::parse('2026-11-10 11:00:00', 'UTC'));
        $second = $this->finalize($this->firstCashier, $this->review($this->firstCashier, $this->cableLine(2)), '100');
        $this->travelBack();

        $after = Livewire::actingAs($this->owner)
            ->withQueryParams(['from' => '2026-11-10', 'to' => '2026-11-10'])
            ->test(OperationsDashboard::class);

        $this->assertNotSame($versionBefore, $after->viewData('chartVersion'));
        $this->assertSame($first->totalMinor() + $second->totalMinor(), $after->viewData('ownerTotalMinor'));
        $this->assertNotSame($totalBefore, $after->viewData('ownerTotalMinor'));
        $this->assertSame(
            $after->viewData('ownerTotalMinor'),
            Money::sumUncapped(array_column($after->viewData('series')['buckets'], 'total_minor')),
        );

        $after->set('from', '2026-11-11')->set('to', '2026-11-10')
            ->assertViewHas('rangeError', 'reversed')
            ->assertViewHas('chartPayload', null)
            ->set('from', '2026-11-10')->set('to', '2026-11-10')
            ->assertViewHas('rangeError', null)
            ->assertViewHas('ownerTotalMinor', $first->totalMinor() + $second->totalMinor());
    }

    #[Test]
    public function hourly_interval_drill_down_matches_history_and_csv(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-10 08:15:00', Sale::DISPLAY_TIMEZONE)->utc());
        $morning = $this->finalize($this->firstCashier, $this->review($this->firstCashier, $this->cableLine(1)), '50');
        $this->travelTo(CarbonImmutable::parse('2026-11-10 14:45:00', Sale::DISPLAY_TIMEZONE)->utc());
        $afternoon = $this->finalize($this->firstCashier, $this->review($this->firstCashier, $this->cableLine(2)), '100');
        $this->travelBack();

        $series = RecordedSalesReport::series(
            [$this->store->id],
            LocalDateRange::date('2026-11-10'),
            LocalDateRange::date('2026-11-10'),
        );
        $morningBucket = collect($series['buckets'])->firstWhere('count', 1);
        $afternoonBucket = collect($series['buckets'])->filter(fn (array $bucket): bool => $bucket['count'] === 1)->values()->get(1);
        $this->assertNotNull($morningBucket);
        $this->assertNotNull($afternoonBucket);
        $this->assertTrue($morningBucket['uses_interval']);
        $this->assertNotSame($morningBucket['start_at'], $afternoonBucket['start_at']);

        $morningParams = RecordedSalesReport::bucketHistoryParams($morningBucket);
        $history = Livewire::actingAs($this->owner)
            ->withQueryParams($morningParams)
            ->test(SalesHistory::class, ['store' => $this->store]);
        $this->assertSame(1, $history->viewData('summary')['count']);
        $this->assertSame($morning->totalMinor(), $history->viewData('summary')['total_minor']);
        $history->assertSee($morning->number)->assertDontSee($afternoon->number);

        $afternoonHistory = Livewire::actingAs($this->owner)
            ->withQueryParams(RecordedSalesReport::bucketHistoryParams($afternoonBucket))
            ->test(SalesHistory::class, ['store' => $this->store]);
        $this->assertSame($afternoon->totalMinor(), $afternoonHistory->viewData('summary')['total_minor']);
        $afternoonHistory->assertSee($afternoon->number)->assertDontSee($morning->number);

        $csv = $this->actingAs($this->owner)
            ->get(route('pos.sales.export', [$this->store, ...$morningParams]));
        $csv->assertOk();
        $body = $csv->streamedContent();
        $this->assertStringContainsString($morning->number, $body);
        $this->assertStringNotContainsString($afternoon->number, $body);

        $this->actingAs($this->owner)
            ->get(route('pos.sales.export', [$this->store, 'from_at' => 'not-a-time', 'to_at' => '2026-11-10T12:00:00Z']))
            ->assertStatus(422);

        $dashboard = Livewire::actingAs($this->owner)
            ->withQueryParams(['from' => '2026-11-10', 'to' => '2026-11-10'])
            ->test(OperationsDashboard::class, ['store' => $this->store]);
        $links = $dashboard->viewData('chartPayload')['time']['links'];
        $this->assertTrue(collect($links)->contains(
            fn (string $url): bool => str_contains(urldecode($url), 'from_at='.$morningBucket['start_at']),
        ));
    }

    #[Test]
    public function hourly_interval_is_visible_cleared_by_date_change_and_aligned_with_csv(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-10 08:15:00', Sale::DISPLAY_TIMEZONE)->utc());
        $morning = $this->finalize($this->firstCashier, $this->review($this->firstCashier, $this->cableLine(1)), '50');
        $this->travelTo(CarbonImmutable::parse('2026-11-10 14:45:00', Sale::DISPLAY_TIMEZONE)->utc());
        $afternoon = $this->finalize($this->firstCashier, $this->review($this->firstCashier, $this->cableLine(2)), '100');
        $this->travelTo(CarbonImmutable::parse('2026-11-11 10:00:00', Sale::DISPLAY_TIMEZONE)->utc());
        $nextDay = $this->finalize($this->firstCashier, $this->review($this->firstCashier, $this->cableLine(1)), '50');
        $this->travelBack();

        $morningBucket = collect(RecordedSalesReport::series(
            [$this->store->id],
            LocalDateRange::date('2026-11-10'),
            LocalDateRange::date('2026-11-10'),
        )['buckets'])->firstWhere('count', '>', 0);
        $params = RecordedSalesReport::bucketHistoryParams($morningBucket);

        $history = Livewire::actingAs($this->owner)
            ->withQueryParams($params)
            ->test(SalesHistory::class, ['store' => $this->store]);

        $interval = $history->viewData('activeInterval');
        $this->assertNotNull($interval);
        $history->assertSee('Exact Istanbul interval')->assertSee('end instant is exclusive');
        $this->assertSame(1, $history->viewData('summary')['count']);
        $history->assertSee($morning->number)->assertDontSee($afternoon->number);

        $history->set('tender', 'cash')->call('applyFilters')
            ->assertSet('fromAt', $params['from_at'])
            ->assertSet('toAt', $params['to_at']);

        $history->call('clearInterval')
            ->assertSet('fromAt', '')
            ->assertSet('toAt', '')
            ->assertSet('from', '2026-11-10')
            ->assertSet('to', '2026-11-10');
        $this->assertNull($history->viewData('activeInterval'));
        $this->assertSame(2, $history->viewData('summary')['count']);
        $history->assertSee($morning->number)->assertSee($afternoon->number)->assertDontSee($nextDay->number);

        $history = Livewire::actingAs($this->owner)
            ->withQueryParams($params)
            ->test(SalesHistory::class, ['store' => $this->store]);
        $history->set('from', '2026-11-11')->set('to', '2026-11-11')->call('applyFilters')
            ->assertSet('fromAt', '')
            ->assertSet('toAt', '')
            ->assertSee($nextDay->number)
            ->assertDontSee($morning->number);

        $this->actingAs($this->owner)
            ->get(route('pos.sales.export', [$this->store, 'from' => 'not-a-date', 'to' => '2026-11-10', 'from_at' => $params['from_at'], 'to_at' => $params['to_at']]))
            ->assertStatus(422);
        Livewire::actingAs($this->owner)
            ->withQueryParams(['from' => 'not-a-date', 'to' => '2026-11-10', 'from_at' => $params['from_at'], 'to_at' => $params['to_at']])
            ->test(SalesHistory::class, ['store' => $this->store])
            ->assertViewHas('rangeError', 'invalid_date')
            ->assertViewHas('summary', null);
    }

    #[Test]
    public function invalid_utc_instants_are_rejected_by_history_and_csv(): void
    {
        $this->assertNull(LocalDateRange::utcInstant('2026-02-30T10:00:00Z'));
        $this->assertNull(LocalDateRange::utcInstant('2026-13-01T10:00:00Z'));
        $this->assertNull(LocalDateRange::utcInstant('2026-11-10T25:00:00Z'));
        $this->assertNull(LocalDateRange::utcInstant('2026-11-10T12:60:00Z'));
        $this->assertNull(LocalDateRange::utcInstant('2026-11-10T12:00:60Z'));
        $this->assertNull(LocalDateRange::utcInstant('2026-11-10 12:00:00Z'));
        $this->assertSame('invalid_interval', LocalDateRange::salesFilterError('', '', '2026-11-10T12:00:00Z', ''));
        $this->assertSame('reversed_interval', LocalDateRange::salesFilterError(
            '2026-11-10',
            '2026-11-10',
            '2026-11-10T13:00:00Z',
            '2026-11-10T12:00:00Z',
        ));

        Livewire::actingAs($this->owner)
            ->withQueryParams(['from' => '2026-11-10', 'to' => '2026-11-10', 'from_at' => '2026-02-30T10:00:00Z', 'to_at' => '2026-02-30T11:00:00Z'])
            ->test(SalesHistory::class, ['store' => $this->store])
            ->assertViewHas('rangeError', 'invalid_interval')
            ->assertSee('exact time interval is not valid');

        $this->actingAs($this->owner)
            ->get(route('pos.sales.export', [
                $this->store,
                'from' => '2026-11-10',
                'to' => '2026-11-10',
                'from_at' => '2026-02-30T10:00:00Z',
                'to_at' => '2026-02-30T11:00:00Z',
            ]))
            ->assertStatus(422);

        $this->actingAs($this->owner)
            ->get(route('pos.sales.export', [
                $this->store,
                'from_at' => '2026-11-10T13:00:00Z',
                'to_at' => '2026-11-10T12:00:00Z',
            ]))
            ->assertStatus(422);
    }

    #[Test]
    public function supplier_payout_links_require_destination_permission_and_mismatches_are_summarized(): void
    {
        $supplier = OperationalSupplier::factory()->create(['name' => 'Anka Attention']);
        $purchase = $this->postedPurchase($this->owner, $this->store, $supplier, [[$this->cable, 1, '100.00']]);
        $session = app(OpenCashboxSession::class)($this->owner, $this->store, '500.00', null);
        app(RecordSupplierPayment::class)(
            $this->owner,
            $purchase,
            $this->paymentInput('60.00', 'PAYOUT-ATTN', ['method' => 'cash']),
            (string) Str::uuid(),
            $session->id,
        );

        DB::table('purchases')->where('id', $purchase->id)->update(['paid_total' => '10.00']);

        $this->revokeFromOwners('manage_cashbox');
        $this->revokeFromOwners('manage_suppliers');
        $owner = $this->owner->fresh();
        $dashboard = Livewire::actingAs($owner)
            ->withQueryParams(['from' => '2026-11-10', 'to' => '2026-11-10'])
            ->test(OperationsDashboard::class);

        $attention = collect($dashboard->viewData('attention'));
        $payout = $attention->firstWhere('key', 'supplier-payout-'.$this->store->id);
        $this->assertNotNull($payout);
        $this->assertNotNull($payout['url']);
        $this->assertStringNotContainsString('/cashbox', $payout['url']);
        $this->assertStringContainsString('/operations/finance', $payout['url']);
        $this->actingAs($owner)->get($payout['url'])->assertOk();

        $mismatch = $attention->firstWhere('key', 'supplier-mismatches');
        $this->assertNotNull($mismatch);
        $this->assertNull($mismatch['store']);
        $this->assertNotNull($mismatch['url']);
        $this->assertStringContainsString('/operations/finance', $mismatch['url']);
        $this->assertStringNotContainsString('/operations/suppliers', parse_url($mismatch['url'], PHP_URL_PATH) ?? '');
        $this->actingAs($owner)->get($mismatch['url'])->assertOk();
        $dashboard->assertSee('supplier ledger mismatch')->assertSee('Combined across the selected branches');
        $dashboard->assertDontSee('Anka Attention');
    }
}
