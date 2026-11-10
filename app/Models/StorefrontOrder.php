<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StorefrontChannel;
use App\Enums\StorefrontOrderStatus;
use App\Services\Purchasing\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A storefront order for linked branch stock, fulfilled by one branch and paid at pickup.
 * The linked legacy order keeps the order history; see StorefrontOrderStatus for the states.
 *
 * @property int $id
 * @property int $order_id
 * @property int $store_id
 * @property int $buyer_id
 * @property int $shop_id
 * @property string $submission_key
 * @property StorefrontOrderStatus $status
 * @property StorefrontChannel $channel
 * @property string $currency
 * @property string $total
 * @property string|null $notes
 * @property int|null $price_change_requested_by
 * @property CarbonInterface|null $price_change_requested_at
 * @property int $price_proposal_version
 * @property string|null $price_proposal_fingerprint
 * @property string|null $accepted_price_proposal
 * @property int|null $rejected_by
 * @property CarbonInterface|null $rejected_at
 * @property string|null $rejection_reason
 * @property string|null $confirmation_fingerprint
 * @property int|null $confirmed_by
 * @property CarbonInterface|null $confirmed_at
 * @property int|null $pickup_hours
 * @property CarbonInterface|null $expires_at
 * @property int|null $sale_id
 * @property CarbonInterface|null $fulfilled_at
 * @property int|null $cancelled_by
 * @property CarbonInterface|null $cancelled_at
 * @property string|null $cancellation_reason
 * @property CarbonInterface|null $expired_at
 */
class StorefrontOrder extends Model
{
    public const MORPH_ALIAS = 'storefront_order';

    public const MAX_NOTE_LENGTH = 500;

    public const DEFAULT_PICKUP_HOURS = 48;

    public const MAX_PICKUP_HOURS = 336;

    protected $fillable = [
        'order_id', 'store_id', 'buyer_id', 'shop_id', 'submission_key', 'status', 'channel', 'currency', 'total', 'notes',
        'price_change_requested_by', 'price_change_requested_at', 'price_proposal_version', 'price_proposal_fingerprint', 'accepted_price_proposal',
        'rejected_by', 'rejected_at', 'rejection_reason', 'confirmation_fingerprint', 'confirmed_by', 'confirmed_at',
        'pickup_hours', 'expires_at', 'sale_id', 'fulfilled_at', 'cancelled_by', 'cancelled_at', 'cancellation_reason', 'expired_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => StorefrontOrderStatus::class,
            'channel' => StorefrontChannel::class,
            'total' => 'decimal:2',
            'pickup_hours' => 'integer',
            'price_proposal_version' => 'integer',
            'price_change_requested_at' => 'datetime',
            'rejected_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'expires_at' => 'datetime',
            'fulfilled_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    public function isPending(): bool
    {
        return $this->status === StorefrontOrderStatus::PENDING;
    }

    public function isConfirmed(): bool
    {
        return $this->status === StorefrontOrderStatus::CONFIRMED;
    }

    public function awaitsPriceAcceptance(): bool
    {
        return $this->isPending() && $this->price_proposal_fingerprint !== null;
    }

    /**
     * Identifies one price proposal: its version and, per line, the proposed price and device.
     * The buyer page sends it back on acceptance, so only the proposal the buyer saw is accepted.
     *
     * @param  iterable<StorefrontOrderLine>  $lines
     */
    public static function proposalFingerprint(int $orderId, int $version, iterable $lines): string
    {
        $proposed = [];

        foreach ($lines as $line) {
            $proposed[] = [$line->id, $line->proposedUnitPriceMinor(), $line->proposed_unit_id];
        }

        return hash('sha256', json_encode(['order' => $orderId, 'version' => $version, 'lines' => $proposed], JSON_THROW_ON_ERROR));
    }

    /**
     * Whether a confirmed order is past its pickup deadline. Its holds still stand while a
     * counter checkout holds the payment claim; see hasPaymentClaim().
     */
    public function isPastPickup(?CarbonInterface $at = null): bool
    {
        return $this->isConfirmed() && $this->expires_at !== null && $this->expires_at->lte($at ?? now());
    }

    public function hasPaymentClaim(): bool
    {
        return $this->lines->contains(fn (StorefrontOrderLine $line): bool => $line->claimed_attempt_id !== null);
    }

    /**
     * The state customers and staff see: a confirmed order past its deadline without a payment
     * claim reads as expired before anything stores it.
     */
    public function displayStatus(): StorefrontOrderStatus
    {
        return $this->isPastPickup() && ! $this->hasPaymentClaim() ? StorefrontOrderStatus::EXPIRED : $this->status;
    }

    public function totalMinor(): int
    {
        return Money::fromDecimal((string) $this->total);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * @return HasMany<StorefrontOrderLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(StorefrontOrderLine::class)->orderBy('id');
    }
}
