<?php

declare(strict_types=1);

namespace App\Livewire\Backend\Stores;

use Illuminate\Support\Facades\App;

/**
 * Store management screens are translated into Arabic and English only;
 * any other active locale falls back to Arabic.
 */
final class StoreManagementLocale
{
    public const FALLBACK = 'ar';

    /** @var list<string> */
    public const SUPPORTED = ['ar', 'en'];

    /** @var list<string> */
    private const RTL = ['ar'];

    public static function resolve(): string
    {
        $locale = App::getLocale();

        return in_array($locale, self::SUPPORTED, true) ? $locale : self::FALLBACK;
    }

    public static function apply(): string
    {
        $locale = self::resolve();
        App::setLocale($locale);

        return $locale;
    }

    public static function direction(?string $locale = null): string
    {
        return in_array($locale ?? self::resolve(), self::RTL, true) ? 'rtl' : 'ltr';
    }
}
