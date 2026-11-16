<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Network\ApproveReservation;
use App\Actions\Network\PublishUnitListing;
use App\Actions\Network\RequestReservation;
use App\Actions\Network\SetReservationHold;
use App\Livewire\Backend\Reports\OperationalReview;
use App\Models\NetworkListing;
use App\Models\Store;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\InteractsWithPos;
use Tests\TestCase;

class RequestedAtImmutabilityTest extends TestCase
{
    use InteractsWithPos;
    use RefreshDatabase;

    private Store $other;

    private User $otherCashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCounter();
        $this->useEnglishLocale();
        $this->other = Store::factory()->create(['code' => 'KT02', 'name' => 'Karman Kadikoy']);
        $this->otherCashier = $this->createCashier($this->other, name: 'Kadikoy cashier');
        app(SetReservationHold::class)($this->owner, $this->store, 60);
    }

    #[Test]
    public function requested_at_has_no_on_update_and_survives_lifecycle_and_direct_updates(): void
    {
        $meta = DB::selectOne('SELECT @@explicit_defaults_for_timestamp AS edt, @@version AS v');
        $this->assertNotNull($meta);
        $create = DB::selectOne('SHOW CREATE TABLE network_reservations')->{'Create Table'};
        $this->assertMatchesRegularExpression('/`requested_at` timestamp NOT NULL DEFAULT (?:current_timestamp\(\)|CURRENT_TIMESTAMP)/i', $create);
        $this->assertDoesNotMatchRegularExpression('/`requested_at`[^,\n]*ON UPDATE/i', $create);

        $this->travelTo(CarbonImmutable::parse('2026-11-10 12:00:00', 'Europe/Istanbul'));
        $listing = app(PublishUnitListing::class)($this->owner, $this->store, $this->phone->id, NetworkListing::UNLISTED_REVISION);
        $reservation = app(RequestReservation::class)($this->otherCashier, $this->other, $listing->id, $listing->revision, null);
        $requestedAt = $reservation->requested_at->copy();

        $this->travelTo(CarbonImmutable::parse('2026-11-12 15:30:00', 'Europe/Istanbul'));
        app(ApproveReservation::class)($this->owner, $this->store, $reservation->id);

        $fresh = $reservation->fresh();
        $this->assertTrue($requestedAt->equalTo($fresh->requested_at));
        $this->assertTrue($fresh->decided_at->equalTo(now()));
        $this->assertFalse($fresh->requested_at->equalTo($fresh->decided_at));

        DB::table('network_reservations')->where('id', $fresh->id)->update([
            'decision_note' => 'direct db touch',
        ]);
        $this->assertTrue($requestedAt->equalTo($reservation->fresh()->requested_at));

        $reportRequestDay = Livewire::actingAs($this->owner)
            ->withQueryParams(['from' => '2026-11-10', 'to' => '2026-11-10'])
            ->test(OperationalReview::class);
        $incomingRequestDay = collect($reportRequestDay->viewData('network')['incoming'])
            ->first(fn (array $row): bool => $row['store']->is($this->store));
        $this->assertSame(1, $incomingRequestDay['period']['requested']);
        $this->assertSame(0, $incomingRequestDay['period']['approved']);

        $reportDecisionDay = Livewire::actingAs($this->owner)
            ->withQueryParams(['from' => '2026-11-12', 'to' => '2026-11-12'])
            ->test(OperationalReview::class);
        $incomingDecisionDay = collect($reportDecisionDay->viewData('network')['incoming'])
            ->first(fn (array $row): bool => $row['store']->is($this->store));
        $this->assertSame(0, $incomingDecisionDay['period']['requested']);
        $this->assertSame(1, $incomingDecisionDay['period']['approved']);
    }
}
