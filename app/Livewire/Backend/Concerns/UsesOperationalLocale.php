<?php

declare(strict_types=1);

namespace App\Livewire\Backend\Concerns;

use App\Livewire\Backend\OperationalLocale;

trait UsesOperationalLocale
{
    public function bootUsesOperationalLocale(): void
    {
        OperationalLocale::apply();
    }

    /**
     * Arabic has no framework validation translations, so operational screens use their own.
     *
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
