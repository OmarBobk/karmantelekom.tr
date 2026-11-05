<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * The correction chain that owns a normalized supplier invoice number.
 *
 * @property int $id
 * @property int $operational_supplier_id
 * @property string $number_key
 * @property int $root_purchase_id
 */
class SupplierInvoiceNumber extends Model
{
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = ['operational_supplier_id', 'number_key', 'root_purchase_id'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Invoice number claims are append-only.'));
        static::deleting(fn () => throw new LogicException('Invoice number claims are append-only.'));
    }
}
