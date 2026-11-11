<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Currency;
use App\Enums\ExpenseCategory;
use App\Enums\ExpenseMethod;
use App\Services\Purchasing\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * A general branch expense in TRY. A cash expense comes from exactly one confirmed cash payout
 * and posts one outgoing cash movement; a card or bank transfer expense comes from exactly one
 * expense submission and posts nothing to the cashbox. Corrections are a separate procedure, so
 * expenses are never edited or deleted.
 *
 * The optional supplier and device references are for traceability only: they change no stock,
 * device cost, or supplier balance.
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
 * @property int|null $operational_supplier_id
 * @property int|null $inventory_unit_id
 * @property int $user_id
 * @property int|null $cash_payout_id
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
        'store_id', 'currency', 'category', 'description', 'amount', 'expense_date', 'method', 'reference',
        'operational_supplier_id', 'inventory_unit_id', 'user_id', 'cash_payout_id', 'recorded_at',
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
     * Expenses whose business date falls on the Istanbul dates $from through $to, inclusive. The
     * business date is the one recorded on the expense, not when it was entered or paid out.
     *
     * @param  Builder<Expense>  $query
     */
    public function scopeOnBusinessDays(Builder $query, string $from, string $to): void
    {
        $query->whereBetween('expense_date', [$from, $to]);
    }

    /**
     * Count and total of the expenses in the query, aggregated in SQL.
     *
     * @param  Builder<Expense>  $query
     * @return array{count: int, total_minor: int}
     */
    public static function summary(Builder $query): array
    {
        $row = $query->toBase()->reorder()->selectRaw('count(*) as expense_count, coalesce(sum(amount), 0) as expense_total')->first();

        return ['count' => (int) $row->expense_count, 'total_minor' => Money::fromDecimal((string) $row->expense_total)];
    }

    /**
     * Total per store and category for the business dates, aggregated in SQL. Only confirmed
     * expenses exist as rows; pending and released cash payouts are not expenses.
     *
     * @param  list<int>  $storeIds
     * @return array<int, array<string, int>> minor units by store id, then category value
     */
    public static function totalsByStoreAndCategory(array $storeIds, string $from, string $to): array
    {
        if ($storeIds === []) {
            return [];
        }

        $totals = [];

        self::query()
            ->whereIn('store_id', $storeIds)
            ->onBusinessDays($from, $to)
            ->toBase()
            ->selectRaw('store_id, category, sum(amount) as category_total')
            ->groupBy('store_id', 'category')
            ->get()
            ->each(function (object $row) use (&$totals): void {
                $totals[(int) $row->store_id][(string) $row->category] = Money::fromDecimal((string) $row->category_total);
            });

        return $totals;
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

    /**
     * @return BelongsTo<OperationalSupplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(OperationalSupplier::class, 'operational_supplier_id');
    }

    /**
     * @return BelongsTo<InventoryUnit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(InventoryUnit::class, 'inventory_unit_id');
    }
}
