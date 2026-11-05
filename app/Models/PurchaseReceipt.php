<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * The single full receipt of a posted invoice's goods into its store.
 *
 * @property int $id
 * @property int $purchase_id
 * @property int $store_id
 * @property int $user_id
 * @property string|null $note
 * @property string $idempotency_key
 * @property string $request_fingerprint
 * @property Carbon $received_at
 */
class PurchaseReceipt extends Model
{
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = ['purchase_id', 'store_id', 'user_id', 'note', 'idempotency_key', 'request_fingerprint', 'received_at'];

    /**
     * @var list<string>
     */
    protected $hidden = ['request_fingerprint'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Purchase receipts are append-only.'));
        static::deleting(fn () => throw new LogicException('Purchase receipts are append-only.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['received_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Purchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return HasMany<PurchaseReceiptLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseReceiptLine::class);
    }
}
