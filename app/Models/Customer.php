<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A customer. Counter customers belong to one store and are identified there by their
 * normalized phone number; older rows without a store are outside POS lookup.
 *
 * @property int $id
 * @property int|null $store_id
 * @property string $name
 * @property string|null $phone
 * @property string|null $phone_normalized
 * @property int|null $created_by
 */
class Customer extends Model
{
    use HasFactory;

    public const MAX_NAME_LENGTH = 120;

    public const MAX_PHONE_LENGTH = 30;

    protected $fillable = ['name', 'email', 'phone', 'address', 'store_id', 'phone_normalized', 'created_by'];

    /**
     * Digits of a phone number in international form without the plus sign, or null when the
     * input is not a plausible phone number. Turkish national and mobile forms map to 90…, so
     * 0532 123 45 67, 532 123 45 67, and +90 532 123 45 67 are the same customer.
     */
    public static function normalizePhone(string $phone): ?string
    {
        $phone = strtr($phone, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);

        if (preg_match('/^\s*(\+|00)?[\d\s().\-\/]+$/u', $phone, $matches) !== 1) {
            return null;
        }

        $international = ($matches[1] ?? '') !== '';
        $digits = preg_replace('/\D/', '', $phone);

        if ($international && str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (! $international) {
            if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
                $digits = '90'.substr($digits, 1);
            } elseif (strlen($digits) === 10 && str_starts_with($digits, '5')) {
                $digits = '90'.$digits;
            }
        }

        $length = strlen($digits);

        return $length >= 8 && $length <= 15 && ! str_starts_with($digits, '0') ? $digits : null;
    }

    /**
     * The phone with all but its last four digits hidden, for lists and history.
     */
    public function maskedPhone(): string
    {
        $digits = (string) $this->phone_normalized;

        return $digits === '' ? '' : '••• '.substr($digits, -4);
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return HasMany<Sale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * @return HasMany<CustomerDebt, $this>
     */
    public function debts(): HasMany
    {
        return $this->hasMany(CustomerDebt::class);
    }

    /**
     * @return HasMany<CustomerPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
