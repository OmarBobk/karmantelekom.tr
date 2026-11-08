<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Currency;
use App\Enums\ExpenseCategory;
use App\Enums\ExpenseMethod;
use App\Services\Purchasing\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * A general branch expense in TRY. A cash expense comes from exactly one confirmed cash payout
 * and posts one outgoing cash movement; card and bank transfer expenses carry their own request
 * key and post nothing to the cashbox. Corrections are a separate procedure, so expenses are
 * never edited or deleted.
 *
 * @property int $id
 * @property int $store_id
 * @property Currency $currency
 * @property ExpenseCategory $category
 * @property string $description
 * @property string $amount
 * @property Carbon $expense_date
 * @property ExpenseMethod $method
 * @property string|null $reference
 * @property int $user_id
 * @property int|null $cash_payout_id
 * @property string|null $idempotency_key
 * @property string|null $request_fingerprint
 * @property Carbon $recorded_at
 */
class Expense extends Model
{
    public const MAX_DESCRIPTION_LENGTH = 500;

    public const MAX_REFERENCE_LENGTH = 100;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'store_id', 'currency', 'category', 'description', 'amount', 'expense_date', 'method', 'reference', 'user_id',
        'cash_payout_id', 'idempotency_key', 'request_fingerprint', 'recorded_at',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Expenses are corrected through their own procedure, not edited.'));
        static::deleting(fn () => throw new LogicException('Expenses are kept.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'currency' => Currency::class,
            'category' => ExpenseCategory::class,
            'expense_date' => 'date',
            'method' => ExpenseMethod::class,
            'recorded_at' => 'datetime',
        ];
    }

    public function amountMinor(): int
    {
        return Money::fromDecimal($this->amount);
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
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<CashPayout, $this>
     */
    public function payout(): BelongsTo
    {
        return $this->belongsTo(CashPayout::class, 'cash_payout_id');
    }
}
