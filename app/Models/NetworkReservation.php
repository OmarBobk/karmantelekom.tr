<?php

declare(strict_types=1);

namespace App\Models;

use App\Actions\Storefront\BranchOrderHolds;
use App\Enums\ReservationStatus;
use App\Services\Reporting\LocalDateRange;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\NetworkReservationFactory;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * One branch's request to hold another branch's shared device, and its fulfillment.
 *
 * Expiry is evaluated on every read: an approved reservation past expires_at stops holding the
 * device even before anything stores EXPIRED, except while the device is in transit. The owning
 * branch is the original owner and never changes; the device record's store is where it is now.
 *
 * @property int $id
 * @property int $network_listing_id
 * @property int $listing_revision The listing revision the requester saw.
 * @property int $inventory_unit_id
 * @property int $product_definition_id
 * @property int $owning_store_id
 * @property int $requesting_store_id
 * @property ReservationStatus $status
 * @property int|null $active_unit_id Set only while approved, so one device has one active reservation.
 * @property int $requested_by
 * @property Carbon $requested_at
 * @property string|null $request_note
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property string|null $decision_note
 * @property Carbon|null $expires_at
 * @property int|null $claimed_attempt_id The linked checkout whose payment started; see hasLiveClaim().
 * @property Carbon|null $claimed_at
 * @property int|null $dispatched_by
 * @property Carbon|null $dispatched_at
 * @property int|null $received_by
 * @property Carbon|null $received_at
 * @property int|null $cancelled_by
 * @property int|null $cancelled_store_id
 * @property Carbon|null $cancelled_at
 * @property string|null $cancellation_reason
 * @property Carbon|null $expired_at
 * @property int|null $sale_id
 * @property int|null $fulfilled_by
 * @property int|null $fulfilled_store_id
 * @property Carbon|null $fulfilled_at
 */
class NetworkReservation extends Model
{
    /** @use HasFactory<NetworkReservationFactory> */
    use HasFactory;

    public const MORPH_ALIAS = 'network_reservation';

    public const MAX_NOTE_LENGTH = 500;

    /** Displayed states from displayState(), including final stored statuses. */
    public const DISPLAY_STATES = [
        'pending', 'approved', 'payment_started', 'in_transit', 'received',
        'rejected', 'cancelled', 'expired', 'fulfilled',
    ];

    /** Period activity figures keyed by the timestamp that defines them. */
    public const PERIOD_EVENTS = [
        'requested', 'approved', 'rejected', 'dispatched', 'received', 'cancelled', 'expired', 'fulfilled',
    ];

    /**
     * Current operational-review metric for approved holds that have lapsed and are not yet closed.
     * Distinct from display state "expired", which also includes stored final EXPIRED rows.
     */
    public const CURRENTLY_LAPSED = 'lapsed';

    protected $fillable = [
        'network_listing_id', 'listing_revision', 'inventory_unit_id', 'product_definition_id',
        'owning_store_id', 'requesting_store_id', 'status', 'active_unit_id',
        'requested_by', 'requested_at', 'request_note',
        'decided_by', 'decided_at', 'decision_note', 'expires_at', 'claimed_attempt_id', 'claimed_at',
        'dispatched_by', 'dispatched_at', 'received_by', 'received_at',
        'cancelled_by', 'cancelled_store_id', 'cancelled_at', 'cancellation_reason', 'expired_at',
        'sale_id', 'fulfilled_by', 'fulfilled_store_id', 'fulfilled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReservationStatus::class,
            'listing_revision' => 'integer',
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
            'expires_at' => 'datetime',
            'claimed_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'received_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'expired_at' => 'datetime',
            'fulfilled_at' => 'datetime',
        ];
    }

    public function isApproved(): bool
    {
        return $this->status === ReservationStatus::APPROVED;
    }

    public function isInTransit(): bool
    {
        return $this->isApproved() && $this->dispatched_at !== null && $this->received_at === null;
    }

    public function isExpiredAt(CarbonInterface $at): bool
    {
        return $this->expires_at !== null && $this->expires_at->lte($at);
    }

    /**
     * Approved and before expiry. An in-transit reservation past expiry is not active, but its
     * device stays unavailable until receipt.
     */
    public function isActive(?CarbonInterface $at = null): bool
    {
        return $this->isApproved() && ! $this->isExpiredAt($at ?? now());
    }

    /**
     * Whether ordinary sales of the device are refused at the moment given.
     */
    public function blocksSale(?CarbonInterface $at = null): bool
    {
        return $this->isInTransit() || $this->isActive($at) || $this->hasLiveClaim();
    }

    /**
     * Restrict a device query to devices no approved reservation holds: none unexpired and none
     * claimed by a checkout taking payment. The SQL form of blocksSale(); a device in transit is
     * excluded by its own status. Devices a confirmed storefront order holds are excluded too.
     */
    public static function whereUnitNotHeld(EloquentBuilder|QueryBuilder $query, string $unitColumn): void
    {
        $query->whereNotExists(fn (QueryBuilder $reserved) => $reserved->selectRaw('1')
            ->from('network_reservations')
            ->whereColumn('network_reservations.active_unit_id', $unitColumn)
            ->where('network_reservations.status', ReservationStatus::APPROVED->value)
            ->where(fn (QueryBuilder $held) => $held->where('network_reservations.expires_at', '>', now())->orWhereNotNull('network_reservations.claimed_attempt_id')));

        BranchOrderHolds::whereUnitNotHeld($query, $unitColumn);
    }

    /**
     * A checkout linked to this reservation may sell the device while the reservation is active,
     * or after expiry when it holds the payment claim. A live claim of another checkout refuses it.
     */
    public function allowsLinkedSale(CheckoutAttempt $attempt, ?CarbonInterface $at = null): bool
    {
        if (! $this->isApproved() || $this->isInTransit() || $this->hasLiveClaim(except: $attempt->id)) {
            return false;
        }

        return $this->isActive($at) || $this->claimed_attempt_id === $attempt->id;
    }

    /**
     * Whether a linked checkout other than the one given holds the payment claim. The claim is
     * set when that checkout starts payment and cleared only by fulfillment or by the resolution
     * flows that settle its funds, so the locked row alone decides.
     */
    public function hasLiveClaim(?int $except = null): bool
    {
        return $this->isApproved() && $this->claimed_attempt_id !== null && $this->claimed_attempt_id !== $except;
    }

    /**
     * Status as an operator sees it, with read-time expiry and transit applied.
     *
     * Precedence: transit, payment claim, active approved/received, expiry, then the stored status.
     */
    public function displayState(?CarbonInterface $at = null): string
    {
        if ($this->isInTransit()) {
            return 'in_transit';
        }

        if ($this->isApproved() && $this->claimed_attempt_id !== null) {
            return 'payment_started';
        }

        if ($this->isApproved()) {
            return $this->isActive($at) ? ($this->received_at !== null ? 'received' : 'approved') : 'expired';
        }

        return $this->status->value;
    }

    /**
     * Rows whose displayState($at) equals $state. Keep in lockstep with displayState().
     *
     * @param  EloquentBuilder<NetworkReservation>  $query
     * @return EloquentBuilder<NetworkReservation>
     */
    public function scopeInDisplayState(EloquentBuilder $query, string $state, ?CarbonInterface $at = null): EloquentBuilder
    {
        if (! in_array($state, self::DISPLAY_STATES, true)) {
            throw new InvalidArgumentException("Unknown reservation display state [{$state}].");
        }

        $at ??= now();
        $approved = ReservationStatus::APPROVED->value;

        return match ($state) {
            'pending' => $query->where('status', ReservationStatus::PENDING),
            'rejected' => $query->where('status', ReservationStatus::REJECTED),
            'cancelled' => $query->where('status', ReservationStatus::CANCELLED),
            'fulfilled' => $query->where('status', ReservationStatus::FULFILLED),
            'in_transit' => $query->where('status', $approved)->whereNotNull('dispatched_at')->whereNull('received_at'),
            'payment_started' => $query->where('status', $approved)
                ->whereNotNull('claimed_attempt_id')
                ->where(fn (EloquentBuilder $row) => $row->whereNull('dispatched_at')->orWhereNotNull('received_at')),
            'approved' => $query->where('status', $approved)
                ->whereNull('claimed_attempt_id')
                ->whereNull('dispatched_at')
                ->where(fn (EloquentBuilder $row) => $row->whereNull('expires_at')->orWhere('expires_at', '>', $at)),
            'received' => $query->where('status', $approved)
                ->whereNull('claimed_attempt_id')
                ->whereNotNull('received_at')
                ->where(fn (EloquentBuilder $row) => $row->whereNull('expires_at')->orWhere('expires_at', '>', $at)),
            'expired' => $query->where(fn (EloquentBuilder $row) => $row
                ->where('status', ReservationStatus::EXPIRED)
                ->orWhere(fn (EloquentBuilder $lapsed) => $lapsed->currentlyLapsed($at))),
        };
    }

    /**
     * Approved reservations whose hold has lapsed and are not yet closed as EXPIRED.
     * Shared by the operational-review current metric and its reservation-list drill-down.
     * Excludes mid-transit rows and open payment claims (same precedence as displayState).
     *
     * @param  EloquentBuilder<NetworkReservation>  $query
     * @return EloquentBuilder<NetworkReservation>
     */
    public function scopeCurrentlyLapsed(EloquentBuilder $query, ?CarbonInterface $at = null): EloquentBuilder
    {
        $at ??= now();

        return $query->where('status', ReservationStatus::APPROVED)
            ->whereNull('claimed_attempt_id')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $at)
            ->where(fn (EloquentBuilder $held) => $held->whereNull('dispatched_at')->orWhereNotNull('received_at'));
    }

    /**
     * Period activity for one event, using that event's timestamp within the Istanbul day bounds.
     * Expiry uses expires_at and the current eligibility rules; a later claim release can change a past period.
     *
     * @param  EloquentBuilder<NetworkReservation>  $query
     * @return EloquentBuilder<NetworkReservation>
     */
    public function scopeForPeriodEvent(
        EloquentBuilder $query,
        string $event,
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?CarbonInterface $asOf = null,
    ): EloquentBuilder {
        if (! in_array($event, self::PERIOD_EVENTS, true)) {
            throw new InvalidArgumentException("Unknown reservation period event [{$event}].");
        }

        [$start, $end] = LocalDateRange::utcBounds($from, $to);
        $asOf ??= now();
        $approved = ReservationStatus::APPROVED->value;

        return match ($event) {
            'requested' => $query->where('requested_at', '>=', $start)->where('requested_at', '<', $end),
            'approved' => $query->where('decided_at', '>=', $start)->where('decided_at', '<', $end)->whereNotNull('expires_at'),
            'rejected' => $query->where('decided_at', '>=', $start)->where('decided_at', '<', $end)->where('status', ReservationStatus::REJECTED),
            'dispatched' => $query->where('dispatched_at', '>=', $start)->where('dispatched_at', '<', $end),
            'received' => $query->where('received_at', '>=', $start)->where('received_at', '<', $end),
            'cancelled' => $query->where('cancelled_at', '>=', $start)->where('cancelled_at', '<', $end),
            'fulfilled' => $query->where('fulfilled_at', '>=', $start)->where('fulfilled_at', '<', $end),
            'expired' => $query->where('expires_at', '>=', $start)->where('expires_at', '<', $end)
                ->where('expires_at', '<=', $asOf)
                ->where(fn (EloquentBuilder $row) => $row
                    ->where('status', ReservationStatus::EXPIRED)
                    ->orWhere(fn (EloquentBuilder $lapsed) => $lapsed
                        ->where('status', $approved)
                        ->whereNull('claimed_attempt_id')
                        ->where(fn (EloquentBuilder $held) => $held->whereNull('dispatched_at')->orWhereNotNull('received_at')))),
        };
    }

    /**
     * Whether the reservation may still be cancelled: pending, or approved and not yet dispatched.
     */
    public function isCancellable(?CarbonInterface $at = null): bool
    {
        return $this->status === ReservationStatus::PENDING
            || ($this->isActive($at) && $this->dispatched_at === null);
    }

    /**
     * @return BelongsTo<NetworkListing, $this>
     */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(NetworkListing::class, 'network_listing_id');
    }

    /**
     * @return BelongsTo<InventoryUnit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(InventoryUnit::class, 'inventory_unit_id');
    }

    /**
     * @return BelongsTo<ProductDefinition, $this>
     */
    public function productDefinition(): BelongsTo
    {
        return $this->belongsTo(ProductDefinition::class);
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function owningStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'owning_store_id');
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function requestingStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'requesting_store_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function dispatcher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
