<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\OperationalSupplierFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A supplier Karman Telekom purchases operational stock from.
 *
 * Deliberately not named Supplier: legacy e-commerce tests reference an
 * App\Models\Supplier that never existed, and this record must not give it meaning.
 *
 * @property int $id
 * @property string $name
 * @property string|null $contact_name
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $tax_number
 * @property string|null $address
 * @property string|null $notes
 * @property bool $is_active
 */
class OperationalSupplier extends Model
{
    /** @use HasFactory<OperationalSupplierFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'contact_name',
        'phone',
        'email',
        'tax_number',
        'address',
        'notes',
        'is_active',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['is_active' => true];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * @return HasMany<Purchase, $this>
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    /**
     * @return HasMany<SupplierLedgerEntry, $this>
     */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(SupplierLedgerEntry::class);
    }
}
