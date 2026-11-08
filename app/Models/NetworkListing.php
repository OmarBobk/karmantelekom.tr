<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\NetworkListingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A device a branch has shared on Store Network. It references the device record and never
 * holds stock, identifiers, or costs of its own; withdrawal keeps the row for republishing.
 *
 * @property int $id
 * @property int $inventory_unit_id
 * @property int $store_id
 * @property int $published_by
 * @property Carbon $published_at
 * @property int $revision Increases with every publication and withdrawal.
 * @property int|null $unpublished_by
 * @property Carbon|null $unpublished_at
 */
class NetworkListing extends Model
{
    /** @use HasFactory<NetworkListingFactory> */
    use HasFactory;

    protected $fillable = ['inventory_unit_id', 'store_id', 'published_by', 'published_at', 'unpublished_by', 'unpublished_at', 'revision'];

    /**
     * The revision an operator sees for a device that has never been listed.
     */
    public const UNLISTED_REVISION = 0;

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'unpublished_at' => 'datetime',
            'revision' => 'integer',
        ];
    }

    public function isPublished(): bool
    {
        return $this->unpublished_at === null;
    }

    public function isPublishedBy(Store $store): bool
    {
        return $this->isPublished() && (int) $this->store_id === (int) $store->id;
    }

    /**
     * The listing revision of a device as shown to an operator.
     */
    public static function revisionOf(?self $listing): int
    {
        return $listing?->revision ?? self::UNLISTED_REVISION;
    }

    /**
     * @param  Builder<NetworkListing>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->whereNull('unpublished_at');
    }

    /**
     * @return BelongsTo<InventoryUnit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(InventoryUnit::class, 'inventory_unit_id');
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
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function unpublisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'unpublished_by');
    }
}
