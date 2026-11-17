<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Currency;
use App\Enums\QuantityCostStatus;
use App\Services\Purchasing\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Append-only owner declaration of the total TRY recorded cost for current on-hand quantity.
 *
 * @property int $id
 * @property int $store_id
 * @property int $product_definition_id
 * @property int $inventory_balance_id
 * @property int $quantity
 * @property Currency $currency
 * @property string $declared_total
 * @property QuantityCostStatus $prior_cost_status
 * @property string|null $prior_cost_currency
 * @property string|null $prior_cost_total
 * @property QuantityCostStatus $resulting_cost_status
 * @property string $resulting_cost_currency
 * @property string $resulting_cost_total
 * @property string $reason
 * @property int $user_id
 * @property string $idempotency_key
 * @property string $request_fingerprint
 * @property Carbon $declared_at
 */
class InventoryCostDeclaration extends Model
{
    public const UPDATED_AT = null;

    public const MAX_REASON_LENGTH = 1000;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'store_id',
        'product_definition_id',
        'inventory_balance_id',
        'quantity',
        'currency',
        'declared_total',
        'prior_cost_status',
        'prior_cost_currency',
        'prior_cost_total',
        'resulting_cost_status',
        'resulting_cost_currency',
        'resulting_cost_total',
        'reason',
        'user_id',
        'idempotency_key',
        'request_fingerprint',
        'declared_at',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Cost declarations are append-only.'));
        static::deleting(fn () => throw new LogicException('Cost declarations are append-only.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'currency' => Currency::class,
            'prior_cost_status' => QuantityCostStatus::class,
            'resulting_cost_status' => QuantityCostStatus::class,
            'quantity' => 'integer',
            'declared_at' => 'datetime',
        ];
    }

    public function declaredTotalMinor(): int
    {
        return Money::fromDecimal($this->declared_total);
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<ProductDefinition, $this>
     */
    public function productDefinition(): BelongsTo
    {
        return $this->belongsTo(ProductDefinition::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
