<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Customers\RecordCustomerPayment;
use App\Actions\Inventory\RecordOpeningBalance;
use App\Actions\Pos\ReviewCheckout;
use App\Livewire\Backend\Reports\AuditLog;
use App\Livewire\Backend\Reports\FinanceOverview;
use App\Livewire\Backend\Reports\OperationalReview;
use App\Livewire\Backend\Reports\OperationsDashboard;
use App\Livewire\Pos\SalesHistory;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use App\Services\Purchasing\Money;
use App\Services\Reporting\LocalDateRange;
use App\Services\Reporting\RecordedSalesReport;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\InteractsWithPos;
use Tests\TestCase;

class OwnerDashboardUxTest extends TestCase
{
    use InteractsWithPos;
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
        $emptyPrevious->assertDontSee('%');
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

        $this->revokeFromOwners('manage_cashbox');
        $withoutCashbox = Livewire::actingAs($this->owner->fresh())
            ->withQueryParams(['from' => '2026-11-10', 'to' => '2026-11-10'])
            ->test(OperationsDashboard::class);
        $this->assertSame([], array_filter(
            collect($withoutCashbox->viewData('attention'))->pluck('key')->all(),
            fn (string $key): bool => str_starts_with($key, 'cashbox-'),
        ));

        Livewire::actingAs($this->owner->fresh())
            ->withQueryParams(['from' => '2026-11-11', 'to' => '2026-11-10'])
            ->test(OperationsDashboard::class)
            ->assertViewHas('rangeError', 'reversed')
            ->assertViewHas('chartPayload', null)
            ->assertSee('Current inventory')
            ->assertDontSee('Sales over time')
            ->assertDontSee('Recorded sales overview');
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
}
