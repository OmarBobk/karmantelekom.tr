<?php

declare(strict_types=1);

namespace App\Livewire\Pos;

use App\Livewire\Backend\OperationalLocale;
use Illuminate\Support\Facades\App;

/**
 * The counter's language: the cashier's session choice (Arabic or English), otherwise the
 * operational fallback. Applied on every POS page and Livewire request.
 */
final class PosLocale
{
    public const SESSION_KEY = 'pos_locale';

    public static function apply(): string
    {
        $chosen = session(self::SESSION_KEY);
        $locale = is_string($chosen) && in_array($chosen, OperationalLocale::SUPPORTED, true) ? $chosen : OperationalLocale::resolve();
        App::setLocale($locale);

        return $locale;
    }
}
