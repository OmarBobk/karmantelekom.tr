<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use App\Services\LanguageService;
use Symfony\Component\HttpFoundation\Response;

class HandleLanguagePrefix
{
    private LanguageService $languageService;
    private array $supportedLanguages = ['en', 'tr', 'ar'];

    public function __construct(LanguageService $languageService)
    {
        $this->languageService = $languageService;
    }

    public function handle(Request $request, Closure $next): Response
    {
        // Get locale from route parameter
        $locale = $request->route('locale');

        // Check if the locale is a supported language code
        if ($locale && in_array($locale, $this->supportedLanguages, true)) {
            // Set the language
            $this->languageService->switchLanguage($locale);
            
            // Set the application locale
            App::setLocale($locale);
        } else {
            // Use the current language from session or default
            $currentLang = $this->languageService->getCurrentLanguage();
            App::setLocale($currentLang);
        }

        // Set the direction
        $direction = $this->languageService->getCurrentDirection();
        view()->share('direction', $direction);

        return $next($request);
    }
}

