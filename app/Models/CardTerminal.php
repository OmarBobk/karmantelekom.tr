<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CardTerminalFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An external card terminal at a store counter. The application never talks to it; it only
 * scopes the approval references cashiers copy from its slips.
 *
 * @property int $id
 * @property int $store_id
 * @property string $label
 * @property string $merchant_number
 * @property string $terminal_number
 * @property bool $is_active
 */
class CardTerminal extends Model
{
    /** @use HasFactory<CardTerminalFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = ['store_id', 'label', 'merchant_number', 'terminal_number', 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * @param  Builder<CardTerminal>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function displayName(): string
    {
        return "{$this->label} ({$this->terminal_number})";
    }
}
