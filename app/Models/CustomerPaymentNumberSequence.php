<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The last cash customer-payment receipt number issued by a store.
 *
 * @property int $store_id
 * @property int $last_number
 */
class CustomerPaymentNumberSequence extends Model
{
    protected $primaryKey = 'store_id';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = ['store_id', 'last_number'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['last_number' => 'integer'];
    }
}
