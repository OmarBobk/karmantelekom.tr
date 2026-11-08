<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExpenseMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One submission of the expense form, claiming its request key once across every method. A cash
 * submission links to the payout it reserved; a card or bank transfer submission links to the
 * expense it recorded. A resent identical submission returns this record; anything else under
 * the same key conflicts. Never edited or deleted.
 *
 * @property int $id
 * @property int $store_id
 * @property string $request_key
 * @property ExpenseMethod $method
 * @property string $request_fingerprint
 * @property int $user_id
 * @property int|null $cash_payout_id
 * @property int|null $expense_id
 * @property Carbon $submitted_at
 */
class ExpenseSubmission extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'store_id', 'request_key', 'method', 'request_fingerprint', 'user_id', 'cash_payout_id', 'expense_id', 'submitted_at',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Expense submissions are final.'));
        static::deleting(fn () => throw new LogicException('Expense submissions are kept.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'method' => ExpenseMethod::class,
            'submitted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CashPayout, $this>
     */
    public function payout(): BelongsTo
    {
        return $this->belongsTo(CashPayout::class, 'cash_payout_id');
    }

    /**
     * @return BelongsTo<Expense, $this>
     */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }
}
