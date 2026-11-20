<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Create-submission claim so a lost-response retry recovers the same definition.
 *
 * @property int $id
 * @property int $user_id
 * @property string $request_key
 * @property string $request_fingerprint
 * @property int $product_definition_id
 * @property Carbon $submitted_at
 */
class ProductDefinitionSubmission extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'request_key',
        'request_fingerprint',
        'product_definition_id',
        'submitted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['submitted_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<ProductDefinition, $this>
     */
    public function productDefinition(): BelongsTo
    {
        return $this->belongsTo(ProductDefinition::class);
    }
}
