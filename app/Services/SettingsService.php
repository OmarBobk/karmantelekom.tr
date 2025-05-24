<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class SettingsService
{

    public function get(string $key, $default = null)
    {
        return $this->getAll()->get($key, $default);
    }

    public function getAll(): Collection
    {
        return Setting::all()->pluck('value', 'key');
    }

    public function getGroup(string $group): Collection
    {
        return Setting::where('group', $group)->get();
    }

    public function set(string $key, $value, string $type = 'string', string $group = 'general', ?string $description = null, bool $isPublic = false): Setting
    {
        return Setting::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'type' => $type,
                'group' => $group,
                'description' => $description,
                'is_public' => $isPublic,
            ]
        );
    }

}
