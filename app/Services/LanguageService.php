<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use App\Exceptions\LanguageNotSupportedException;

class LanguageService
{
    private array $supportedLanguages = ['en', 'tr', 'ar'];
    private array $languageConfig;

    public function __construct()
    {
        $this->languageConfig = $this->getLanguageConfig();
    }

    public function switchLanguage(string $code): void
    {
        if (!in_array($code, $this->supportedLanguages, true)) {
            throw new LanguageNotSupportedException("Language {$code} is not supported");
        }

        $locale = $this->languageConfig[$code]['locale'];
        $direction = $this->languageConfig[$code]['direction'];

        // Set the application locale
        App::setLocale($locale);

        // Update session
        Session::put('locale', $code);
        Session::put('direction', $direction);

        // Force refresh of the application locale
        $this->refreshLocale();
    }

    private function refreshLocale(): void
    {
        // Force the application to reload the locale
        App::setLocale(Session::get('locale', 'en'));
    }

    public function getCurrentLanguage(): string
    {
        return Session::get('locale', 'en');
    }

    public function getCurrentDirection(): string
    {
        return Session::get('direction', 'ltr');
    }

    private function getLanguageConfig(): array
    {
        return [
            'en' => [
                'name' => 'English',
                'flag' => 'languages/en.svg',
                'direction' => 'ltr',
                'locale' => 'en'
            ],
            'tr' => [
                'name' => 'Turkish',
                'flag' => 'languages/tr.png',
                'direction' => 'ltr',
                'locale' => 'tr'
            ],
            'ar' => [
                'name' => 'Arabic',
                'flag' => 'languages/ar.svg',
                'direction' => 'rtl',
                'locale' => 'ar'
            ]
        ];
    }

    public function getLanguageName(string $code): string
    {
        return $this->languageConfig[$code]['name'] ?? 'Unknown';
    }

    public function getLanguageFlag(string $code): string
    {
        return $this->languageConfig[$code]['flag'] ?? 'languages/en.svg';
    }

    public function getLanguageDirection(string $code): string
    {
        return $this->languageConfig[$code]['direction'] ?? 'ltr';
    }
}
