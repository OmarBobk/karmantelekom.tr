<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    /** @use HasFactory<\Database\Factories\SettingFactory> */
    use HasFactory;

    protected $fillable = [
        'key',
        'type',
        'group',
        'description',
        'value',
        'is_public',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];


    /**
     * @param $value
     * @return void
     */
    public function setValueAttribute($value): void
    {
        $this->attributes['value'] = match ($this->type) {
            'array', 'json' => is_array($value) ? json_encode($value) : $value,
            default => $value,
        };
    }

}
