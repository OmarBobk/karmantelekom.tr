<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Cashbox\OpenCashboxSession;
use App\Actions\Cashbox\StartSaleCashIntake;
use App\Actions\Network\ApproveReservation;
use App\Actions\Network\CancelReservation;
use App\Actions\Network\DispatchReservation;
use App\Actions\Network\PublishUnitListing;
use App\Actions\Network\ReceiveReservation;
use App\Actions\Network\RejectReservation;
use App\Actions\Network\RequestReservation;
use App\Actions\Network\SetReservationHold;
use App\Actions\Network\SharedListings;
use App\Actions\Network\UnpublishUnitListing;
use App\Actions\Pos\ResolveTerminalPayment;
use App\Actions\Pos\ReviewCheckout;
use App\Enums\MovementType;
use App\Enums\ReservationStatus;
use App\Enums\UnitStatus;
use App\Livewire\Pos\NetworkListings;
use App\Livewire\Pos\NetworkReservations;
use App\Livewire\Pos\PosCounter;
use App\Models\CheckoutAttempt;
use App\Models\InventoryMovement;
use App\Models\NetworkListing;
use App\Models\NetworkReservation;
use App\Models\Store;
use App\Models\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\InteractsWithPos;
use Tests\TestCase;

class StoreNetworkReservationTest extends TestCase
{
    use InteractsWithPos;
    use RefreshDatabase;

    private Store $otherStore;

    private User $otherCashier;

    private User $fatihCashier;

    private NetworkListing $listing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCounter();
        $this->useEnglishLocale();
        $this->travelTo(now()->startOfMinute());
        $this->otherStore = Store::factory()->create(['code' => 'KT02', 'name' => 'Karman Kadikoy']);
        $this->otherCashier = $this->createCashier($this->otherStore, name: 'Kadikoy cashier');
        $this->fatihCashier = $this->createCashier($this->store, name: 'Fatih cashier');
        $this->listing = app(PublishUnitListing::class)($this->owner, $this->store, $this->phone->id, NetworkListing::UNLISTED_REVISION);
        app(SetReservationHold::class)($this->owner, $this->store, 60);
    }

    #[Test]
    public function a_pending_request_is_bound_to_the_seen_revision_and_leaves_the_device_on_sale(): void
    {
        $this->assertReservationError(fn () => $this->request(revision: 0), 'listing changed after you opened the page');

        $reservation = $this->request();
        $this->assertSame(ReservationStatus::PENDING, $reservation->status);
        $this->assertSame(1, $reservation->listing_revision);
        $this->assertSame($this->store->id, $reservation->owning_store_id);
        $this->assertSame($this->otherStore->id, $reservation->requesting_store_id);
        $this->assertNull($reservation->active_unit_id);

        $this->assertSame($reservation->id, $this->request()->id);
        $this->assertSame(1, NetworkReservation::count());
        $this->assertSame(1, $this->auditEvents('reservation_requested'));
        $this->assertSame(1, app(SharedListings::class)($this->otherStore)->total());

        $sale = $this->finalize($this->fatihCashier, $this->review($this->fatihCashier, $this->phoneLine()), '12500.00');
        $this->assertSame(UnitStatus::SOLD, $this->phone->fresh()->status);
        $this->assertNotNull($sale->id);

        $this->assertReservationError(fn () => $this->approve($reservation), 'The device is Sold');
        $this->assertSame(ReservationStatus::PENDING, $reservation->fresh()->status);
    }

    #[Test]
    public function approval_holds_the_device_for_the_owning_branch_time_and_expires_without_a_job(): void
    {
        $reservation = $this->request();
        $this->store->forceFill(['reservation_minutes' => null])->save();
        $this->assertReservationError(fn () => $this->approve($reservation), 'reservation time before approving');

        app(SetReservationHold::class)($this->owner, $this->store, 90);
        $approved = $this->approve($reservation);
        $this->assertSame(ReservationStatus::APPROVED, $approved->status);
        $this->assertSame($this->phone->id, $approved->active_unit_id);
        $this->assertTrue($approved->expires_at->equalTo(now()->addMinutes(90)));
        $this->assertSame($this->owner->id, $approved->decided_by);

        $this->travel(1)->minutes();
        $this->approve($reservation);
        $this->assertTrue($reservation->fresh()->expires_at->equalTo($approved->expires_at));
        $this->assertSame(1, $this->auditEvents('reservation_approved'));

        $this->assertReservationError(fn () => $this->review($this->fatihCashier, $this->phoneLine()), 'reserved for Karman Kadikoy (KT02)');
        $this->assertSame(0, app(SharedListings::class)($this->otherStore)->total());

        app(UnpublishUnitListing::class)($this->owner, $this->store, $this->phone->id, 1);
        $this->assertTrue($reservation->fresh()->isActive());

        $this->travel(90)->minutes();
        $this->assertFalse($reservation->fresh()->isActive());
        $this->assertSame('expired', $reservation->fresh()->displayState());
        $sale = $this->finalize($this->fatihCashier, $this->review($this->fatihCashier, $this->phoneLine()), '12500.00');
        $this->assertSame(UnitStatus::SOLD, $this->phone->fresh()->status);
        $this->assertNull(CheckoutAttempt::query()->findOrFail($sale->checkout_attempt_id)->network_reservation_id);
    }

    #[Test]
    public function only_authorized_users_of_each_branch_can_act_on_a_reservation(): void
    {
        $thirdStore = Store::factory()->create(['code' => 'KT03']);
        $thirdCashier = $this->createCashier($thirdStore, name: 'Third cashier');
        $noPos = $this->createCashier($this->otherStore, withPos: false, name: 'No POS');

        $this->assertDenied(fn () => app(RequestReservation::class)($noPos, $this->otherStore, $this->listing->id, 1));
        $this->assertReservationError(fn () => app(RequestReservation::class)($this->fatihCashier, $this->store, $this->listing->id, 1), 'belongs to your branch');

        $reservation = $this->request();

        $this->assertDenied(fn () => app(ApproveReservation::class)($this->otherCashier, $this->store, $reservation->id));
        $this->assertDenied(fn () => app(ApproveReservation::class)($this->fatihCashier, $this->store, $reservation->id));
        $this->assertDenied(fn () => app(RejectReservation::class)($this->fatihCashier, $this->store, $reservation->id, 'No'));
        $this->assertDenied(fn () => app(SetReservationHold::class)($this->fatihCashier, $this->store, 30));
        $this->assertDenied(fn () => app(ApproveReservation::class)($this->otherCashier, $this->otherStore, $reservation->id));
        $this->assertDenied(fn () => app(ApproveReservation::class)($this->owner, $this->otherStore, $reservation->id), ModelNotFoundException::class);
        $this->assertDenied(fn () => app(CancelReservation::class)($thirdCashier, $thirdStore, $reservation->id, 'Not ours'), ModelNotFoundException::class);
        $this->assertDenied(fn () => app(CancelReservation::class)($this->fatihCashier, $this->store, $reservation->id, 'Not mine'));

        $this->approve($reservation);
        $this->assertDenied(fn () => app(DispatchReservation::class)($this->otherCashier, $this->store, $reservation->id));
        $this->assertDenied(fn () => app(DispatchReservation::class)($this->fatihCashier, $this->store, $reservation->id));
        $this->dispatchDevice($reservation);
        $this->assertDenied(fn () => app(ReceiveReservation::class)($this->fatihCashier, $this->store, $reservation->id), ModelNotFoundException::class);
        $this->assertDenied(fn () => app(ReceiveReservation::class)($thirdCashier, $thirdStore, $reservation->id), ModelNotFoundException::class);
        $this->assertSame(UnitStatus::IN_TRANSIT, $this->phone->fresh()->status);
    }

    #[Test]
    public function payment_already_started_refuses_approval_and_an_approval_refuses_a_later_payment_start(): void
    {
        $terminal = $this->createTerminal();
        $started = $this->startTerminalPayment($this->fatihCashier, $this->review($this->fatihCashier, $this->phoneLine()), $terminal, '12500.00');
        $reservation = $this->request();

        $this->assertReservationError(fn () => $this->approve($reservation), 'already started taking payment');
        $this->assertSame(ReservationStatus::PENDING, $reservation->fresh()->status);
        $this->assertNotNull($started->fresh()->frozen_at);
    }

    #[Test]
    public function an_approval_refuses_a_payment_start_for_a_checkout_reviewed_before_it(): void
    {
        $terminal = $this->createTerminal();
        $attempt = $this->review($this->fatihCashier, $this->phoneLine());
        $this->approve($this->request());

        $this->assertReservationError(fn () => $this->startTerminalPayment($this->fatihCashier, $attempt, $terminal, '12500.00'), 'reserved for Karman Kadikoy (KT02)');
        $this->assertNull($attempt->fresh()->frozen_at);
        $this->assertReservationError(fn () => $this->finalize($this->fatihCashier, $attempt, '12500.00'), 'reserved for Karman Kadikoy (KT02)');
        $this->assertSame(UnitStatus::AVAILABLE, $this->phone->fresh()->status);
    }

    #[Test]
    public function a_linked_payment_started_before_expiry_completes_the_pickup_after_expiry_once(): void
    {
        $terminal = $this->createTerminal();
        $reservation = $this->approve($this->request());
        $attempt = $this->reviewLinked($this->fatihCashier, $this->store, $reservation);
        $this->approveCard($this->fatihCashier, $attempt, $terminal, '12500.00');

        $this->assertReservationError(fn () => $this->cancel($this->otherCashier, $this->otherStore, $reservation), 'already started taking payment');

        $this->travel(61)->minutes();
        $this->assertFalse($reservation->fresh()->isActive());
        $this->assertSame($reservation->id, $this->request()->id);
        $this->assertReservationError(fn () => $this->review($this->owner, $this->phoneLine()), 'is taking payment');

        $sale = $this->finalize($this->fatihCashier, $attempt->fresh());
        $replay = $this->finalize($this->fatihCashier, $attempt->fresh());
        $this->assertSame($sale->id, $replay->id);

        $fulfilled = $reservation->fresh();
        $this->assertSame(ReservationStatus::FULFILLED, $fulfilled->status);
        $this->assertSame($sale->id, $fulfilled->sale_id);
        $this->assertSame($this->store->id, $fulfilled->fulfilled_store_id);
        $this->assertNull($fulfilled->active_unit_id);
        $this->assertSame(1, $this->auditEvents('reservation_fulfilled'));
        $this->assertSame(1, $sale->payments()->count());
    }

    #[Test]
    public function only_one_of_two_checkouts_linked_to_a_reservation_can_take_payment_at_a_time(): void
    {
        $terminal = $this->createTerminal();
        $reservation = $this->approve($this->request());
        $first = $this->reviewLinked($this->fatihCashier, $this->store, $reservation);
        $second = $this->reviewLinked($this->owner, $this->store, $reservation);

        $paymentKey = $this->startTerminalPayment($this->fatihCashier, $first, $terminal, '12500.00')->terminal_payment_key;
        $this->assertSame($first->id, $reservation->fresh()->claimed_attempt_id);
        $this->assertSame('payment_started', $reservation->fresh()->displayState());
        $this->assertSame(1, $this->auditEvents('reservation_claimed'));

        $this->assertReservationError(fn () => $this->startTerminalPayment($this->owner, $second, $terminal, '12500.00'), 'not active for a sale');
        $this->assertReservationError(fn () => $this->finalize($this->owner, $second, '12500.00'), 'not active for a sale');
        $this->assertReservationError(fn () => $this->reviewLinked($this->owner, $this->store, $reservation), 'not active for a sale');
        $this->assertNull($second->fresh()->frozen_at);

        app(ResolveTerminalPayment::class)($this->owner, $this->store, $first->key, $paymentKey, 'no_charge', null, 'Terminal declined');
        $this->assertNull($reservation->fresh()->claimed_attempt_id);
        $this->assertSame(1, $this->auditEvents('reservation_claim_released'));

        $this->approveCard($this->owner, $second, $terminal, '10000.00');
        $this->assertSame($second->id, $reservation->fresh()->claimed_attempt_id);
        $this->assertReservationError(fn () => $this->startTerminalPayment($this->fatihCashier, $first->fresh(), $terminal, '12500.00'), 'not active for a sale');

        $this->travel(61)->minutes();
        app(OpenCashboxSession::class)($this->owner, $this->store, '0', null);
        $intake = app(StartSaleCashIntake::class)($this->owner, $this->store, $second->key, $second->fresh()->revision, (string) Str::uuid());
        $sale = $this->finalize($this->owner, $second->fresh(), '2500', $intake->id);

        $fulfilled = $reservation->fresh();
        $this->assertSame(ReservationStatus::FULFILLED, $fulfilled->status);
        $this->assertSame($sale->id, $fulfilled->sale_id);
        $this->assertSame($second->id, $fulfilled->claimed_attempt_id);
        $this->assertSame(2, $sale->payments()->count());
    }

    #[Test]
    public function an_ordinary_checkout_stays_refused_after_expiry_while_the_linked_payment_is_unresolved(): void
    {
        $terminal = $this->createTerminal();
        $ordinary = $this->review($this->fatihCashier, $this->phoneLine());
        $reservation = $this->approve($this->request());
        $linked = $this->reviewLinked($this->fatihCashier, $this->store, $reservation);
        $this->startTerminalPayment($this->fatihCashier, $linked, $terminal, '12500.00');

        $this->travel(61)->minutes();
        $this->assertFalse($reservation->fresh()->isActive());
        $this->assertTrue($reservation->fresh()->blocksSale());

        $this->assertReservationError(fn () => $this->startTerminalPayment($this->fatihCashier, $ordinary, $terminal, '12500.00'), 'is taking payment');
        $this->assertReservationError(fn () => $this->finalize($this->fatihCashier, $ordinary, '12500.00'), 'is taking payment');
        $this->assertReservationError(fn () => $this->review($this->fatihCashier, $this->phoneLine()), 'is taking payment');
        $this->assertReservationError(fn () => $this->dispatchDevice($reservation), 'already started taking payment');
        $this->assertSame(UnitStatus::AVAILABLE, $this->phone->fresh()->status);

        $this->approveCard($this->fatihCashier, $linked, $terminal, '12500.00');
        $sale = $this->finalize($this->fatihCashier, $linked->fresh());
        $this->assertSame(ReservationStatus::FULFILLED, $reservation->fresh()->status);
        $this->assertSame(UnitStatus::SOLD, $this->phone->fresh()->status);
        $this->assertSame($this->store->id, $sale->store_id);
    }

    #[Test]
    public function checkouts_reviewed_before_dispatch_or_receipt_cannot_take_payment_for_the_moved_device(): void
    {
        $terminal = $this->createTerminal();
        $ordinary = $this->review($this->fatihCashier, $this->phoneLine());
        $reservation = $this->approve($this->request());
        $pickup = $this->reviewLinked($this->fatihCashier, $this->store, $reservation);

        $this->dispatchDevice($reservation);
        $this->assertReservationError(fn () => $this->startTerminalPayment($this->fatihCashier, $pickup, $terminal, '12500.00'), 'changed since review (In transit)');
        $this->assertReservationError(fn () => app(StartSaleCashIntake::class)($this->fatihCashier, $this->store, $pickup->key, $pickup->revision, (string) Str::uuid()), 'changed since review (In transit)');
        $this->assertNull($pickup->fresh()->frozen_at);

        $this->travel(2)->hours();
        $this->receive($reservation);
        $this->assertSame(ReservationStatus::EXPIRED, $reservation->fresh()->status);

        $this->assertReservationError(fn () => $this->startTerminalPayment($this->fatihCashier, $ordinary, $terminal, '12500.00'), 'changed since review (At another branch)');
        $this->assertReservationError(fn () => app(StartSaleCashIntake::class)($this->fatihCashier, $this->store, $ordinary->key, $ordinary->revision, (string) Str::uuid()), 'changed since review (At another branch)');
        $this->assertThrows(fn () => $this->finalize($this->fatihCashier, $ordinary, '12500.00'), ValidationException::class);
        $this->assertNull($ordinary->fresh()->frozen_at);

        $unit = $this->phone->fresh();
        $this->assertSame([$this->otherStore->id, UnitStatus::AVAILABLE], [$unit->store_id, $unit->status]);
        $sale = $this->finalize($this->otherCashier, $this->review($this->otherCashier, $this->phoneLine($unit), store: $this->otherStore), '12500.00');
        $this->assertSame(UnitStatus::SOLD, $unit->fresh()->status);
        $this->assertSame($this->otherStore->id, $sale->store_id);
    }

    #[Test]
    public function cancellation_needs_a_reason_and_stops_at_dispatch(): void
    {
        $reservation = $this->request();
        $this->assertReservationError(fn () => $this->cancel($this->otherCashier, $this->otherStore, $reservation, '  '), 'Enter a reason.', 'cancellation_reason');

        $this->cancel($this->otherCashier, $this->otherStore, $reservation, 'Customer changed their mind');
        $this->cancel($this->otherCashier, $this->otherStore, $reservation, 'Customer changed their mind');
        $cancelled = $reservation->fresh();
        $this->assertSame(ReservationStatus::CANCELLED, $cancelled->status);
        $this->assertSame($this->otherStore->id, $cancelled->cancelled_store_id);
        $this->assertSame('Customer changed their mind', $cancelled->cancellation_reason);
        $this->assertSame(1, $this->auditEvents('reservation_cancelled'));

        $second = $this->approve($this->request());
        $this->dispatchDevice($second);
        $this->assertReservationError(fn () => $this->cancel($this->owner, $this->store, $second), 'can no longer be cancelled');
    }

    #[Test]
    public function a_transfer_is_dispatched_received_and_sold_under_the_reservation_with_safe_retries(): void
    {
        $reservation = $this->approve($this->request());
        $before = InventoryMovement::count();

        $this->dispatchDevice($reservation);
        $this->dispatchDevice($reservation);
        $unit = $this->phone->fresh();
        $this->assertSame(UnitStatus::IN_TRANSIT, $unit->status);
        $this->assertSame($this->store->id, $unit->store_id);
        $this->assertFalse($this->listing->fresh()->isPublished());
        $out = InventoryMovement::query()->where('type', MovementType::TRANSFER_OUT)->sole();
        $this->assertSame([$this->store->id, -1, $this->owner->id], [$out->store_id, $out->quantity_change, $out->user_id]);
        $this->assertSame(1, $this->auditEvents('reservation_dispatched'));
        $this->assertReservationError(fn () => $this->review($this->fatihCashier, $this->phoneLine()), 'This device is In transit and cannot be sold.');

        $this->receive($reservation);
        $this->receive($reservation);
        $unit = $this->phone->fresh();
        $this->assertSame(UnitStatus::AVAILABLE, $unit->status);
        $this->assertSame($this->otherStore->id, $unit->store_id);
        $in = InventoryMovement::query()->where('type', MovementType::TRANSFER_IN)->sole();
        $this->assertSame([$this->otherStore->id, 1, $this->otherCashier->id], [$in->store_id, $in->quantity_change, $in->user_id]);
        $this->assertSame($before + 2, InventoryMovement::count());
        $this->assertSame('received', $reservation->fresh()->displayState());

        $kadikoyColleague = $this->createCashier($this->otherStore, name: 'Kadikoy colleague');
        $this->assertReservationError(fn () => $this->review($kadikoyColleague, $this->phoneLine($unit), store: $this->otherStore), 'reserved for Karman Kadikoy (KT02)');

        $attempt = $this->reviewLinked($this->otherCashier, $this->otherStore, $reservation, $unit);
        $sale = $this->finalize($this->otherCashier, $attempt, '12500.00');
        $replay = $this->finalize($this->otherCashier, $attempt->fresh(), '12500.00');
        $this->assertSame($sale->id, $replay->id);
        $this->assertSame($this->otherStore->id, $sale->store_id);
        $this->assertSame(UnitStatus::SOLD, $unit->fresh()->status);

        $fulfilled = $reservation->fresh();
        $this->assertSame(ReservationStatus::FULFILLED, $fulfilled->status);
        $this->assertSame($sale->id, $fulfilled->sale_id);
        $this->assertSame($this->store->id, $fulfilled->owning_store_id);
        $this->assertSame($this->otherStore->id, $fulfilled->requesting_store_id);
        $this->assertSame($this->otherStore->id, $fulfilled->fulfilled_store_id);
        $this->assertNotNull($fulfilled->dispatched_at);
        $this->assertSame($this->otherCashier->id, $fulfilled->received_by);
        $this->assertSame(1, $this->auditEvents('reservation_received'));
        $this->assertSame(1, $this->auditEvents('reservation_fulfilled'));
    }

    #[Test]
    public function expiry_in_transit_keeps_the_device_unavailable_until_receipt_then_releases_it_there(): void
    {
        $reservation = $this->approve($this->request());
        $this->dispatchDevice($reservation);
        $this->travel(2)->hours();

        $this->assertTrue($reservation->fresh()->isInTransit());
        $this->assertSame(UnitStatus::IN_TRANSIT, $this->phone->fresh()->status);
        $this->assertReservationError(fn () => $this->review($this->fatihCashier, $this->phoneLine()), 'This device is In transit and cannot be sold.');
        $this->assertSame($reservation->id, $this->request()->id);
        $thirdStore = Store::factory()->create(['code' => 'KT03']);
        $thirdCashier = $this->createCashier($thirdStore, name: 'Third cashier');
        $this->assertReservationError(fn () => app(RequestReservation::class)($thirdCashier, $thirdStore, $this->listing->id, 2), 'listing changed after you opened the page');

        $this->receive($reservation);
        $released = $reservation->fresh();
        $this->assertSame(ReservationStatus::EXPIRED, $released->status);
        $this->assertNull($released->active_unit_id);
        $this->assertTrue($released->expired_at->equalTo($released->expires_at));

        $unit = $this->phone->fresh();
        $sale = $this->finalize($this->otherCashier, $this->review($this->otherCashier, $this->phoneLine($unit), store: $this->otherStore), '12500.00');
        $this->assertSame($this->otherStore->id, $sale->store_id);
        $this->assertSame(UnitStatus::SOLD, $unit->fresh()->status);
    }

    #[Test]
    public function both_branches_work_reservations_from_their_pages(): void
    {
        Livewire::actingAs($this->otherCashier)->test(NetworkListings::class, ['store' => $this->otherStore])
            ->call('requestDevice', $this->listing->id, 1)
            ->assertHasNoErrors()
            ->assertSee('Requested');
        $reservation = NetworkReservation::query()->sole();

        Livewire::actingAs($this->owner)->test(NetworkReservations::class, ['store' => $this->store])
            ->assertSee('Karman Kadikoy')
            ->assertSee('Awaiting approval')
            ->call('approve', $reservation->id)
            ->assertHasNoErrors()
            ->assertSee('Reserved');
        $this->assertSame(ReservationStatus::APPROVED, $reservation->fresh()->status);

        Livewire::actingAs($this->fatihCashier)->test(NetworkReservations::class, ['store' => $this->store])
            ->assertDontSee('Reservation time (minutes)')
            ->assertDontSee('Dispatch to branch')
            ->assertSee('Sell at counter');

        Livewire::actingAs($this->otherCashier)->test(NetworkReservations::class, ['store' => $this->otherStore])
            ->set('tab', 'outgoing')
            ->assertSee('Karman Fatih')
            ->call('openReason', $reservation->id, 'cancel')
            ->call('submitReason')
            ->assertHasErrors('cancellation_reason');

        Livewire::withQueryParams(['reservation' => $reservation->id])
            ->actingAs($this->fatihCashier)
            ->test(PosCounter::class, ['store' => $this->store])
            ->assertSet('reservationId', $reservation->id)
            ->assertSee('Selling under Store Network reservation #'.$reservation->id)
            ->call('review')
            ->assertHasNoErrors();
        $this->assertSame($reservation->id, CheckoutAttempt::query()->sole()->network_reservation_id);

        Livewire::withQueryParams(['reservation' => $reservation->id])
            ->actingAs($this->otherCashier)
            ->test(PosCounter::class, ['store' => $this->otherStore])
            ->assertSet('reservationId', null);
    }

    private function request(int $revision = 1): NetworkReservation
    {
        return app(RequestReservation::class)($this->otherCashier, $this->otherStore, $this->listing->id, $revision);
    }

    private function approve(NetworkReservation $reservation): NetworkReservation
    {
        return app(ApproveReservation::class)($this->owner, $this->store, $reservation->id);
    }

    private function dispatchDevice(NetworkReservation $reservation): NetworkReservation
    {
        return app(DispatchReservation::class)($this->owner, $this->store, $reservation->id);
    }

    private function receive(NetworkReservation $reservation): NetworkReservation
    {
        return app(ReceiveReservation::class)($this->otherCashier, $this->otherStore, $reservation->id);
    }

    private function cancel(User $actor, Store $store, NetworkReservation $reservation, string $reason = 'No longer needed'): NetworkReservation
    {
        return app(CancelReservation::class)($actor, $store, $reservation->id, $reason);
    }

    private function reviewLinked(User $actor, Store $store, NetworkReservation $reservation, mixed $unit = null): CheckoutAttempt
    {
        return app(ReviewCheckout::class)($actor, $store, $this->phoneLine($unit ?? $this->phone), reservationId: $reservation->id);
    }

    private function auditEvents(string $event): int
    {
        return Activity::query()->where('log_name', 'store_network')->where('event', $event)->count();
    }

    private function assertReservationError(Closure $action, string $message, ?string $key = null): void
    {
        try {
            $action();
        } catch (ValidationException $exception) {
            $errors = $key === null ? collect($exception->errors())->flatten() : collect($exception->errors()[$key] ?? []);
            $this->assertTrue($errors->contains(fn (string $error): bool => str_contains($error, $message)), 'Unexpected errors: '.json_encode($exception->errors()));

            return;
        }

        $this->fail("Expected a validation error containing: {$message}");
    }

    /**
     * @param  class-string<\Throwable>  $exception
     */
    private function assertDenied(Closure $action, string $exception = AuthorizationException::class): void
    {
        try {
            $action();
        } catch (\Throwable $thrown) {
            $this->assertInstanceOf($exception, $thrown);

            return;
        }

        $this->fail("Expected {$exception}");
    }
}
