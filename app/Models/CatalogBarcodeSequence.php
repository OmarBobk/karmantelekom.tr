<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Singleton cursor for shared internal catalog barcodes (row id 1).
 *
 * @property int $id
 * @property int $last_number
 */
class CatalogBarcodeSequence extends Model
{
    public const SINGLETON_ID = 1;

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = ['id', 'last_number'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['last_number' => 'integer'];
    }
}
