<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The largest counter discount an owner lets an employee give, in basis points (1000 = 10%).
 * It applies only in stores where the employee may use the counter, and a used device's
 * minimum selling price replaces it for that device.
 *
 * @property int $id
 * @property int $user_id
 * @property int $max_discount_basis_points
 * @property int $granted_by
 */
class PosDiscountGrant extends Model
{
    public const FULL_BASIS_POINTS = 10000;

    /**
     * @var list<string>
     */
    protected $fillable = ['user_id', 'max_discount_basis_points', 'granted_by'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['max_discount_basis_points' => 'integer'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function percentLabel(): string
    {
        return self::formatBasisPoints($this->max_discount_basis_points);
    }

    public static function formatBasisPoints(int $basisPoints): string
    {
        return rtrim(rtrim(number_format($basisPoints / 100, 2, '.', ''), '0'), '.');
    }
}
