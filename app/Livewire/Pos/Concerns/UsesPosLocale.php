<?php

declare(strict_types=1);

namespace App\Livewire\Pos\Concerns;

use App\Livewire\Backend\OperationalLocale;
use App\Livewire\Pos\PosLocale;

trait UsesPosLocale
{
    public function bootUsesPosLocale(): void
    {
        PosLocale::apply();
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return __('operations.validation');
    }

    /**
     * @return array{locale: string, textDirection: string}
     */
    protected function localeViewData(): array
    {
        return [
            'locale' => OperationalLocale::resolve(),
            'textDirection' => OperationalLocale::direction(),
        ];
    }
}
