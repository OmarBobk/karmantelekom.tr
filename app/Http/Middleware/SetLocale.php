<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use App\Services\LanguageService;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    private LanguageService $languageService;

    public function __construct(LanguageService $languageService)
    {
        $this->languageService = $languageService;
    }

    public function handle(Request $request, Closure $next): Response
    {
        // Get the current language from the session
//        $locale = strtolower($this->languageService->getCurrentLanguage());

        // Set the application locale
        App::setLocale(config('app.locale'));;

        // Set the direction
        $direction = $this->languageService->getCurrentDirection();
        view()->share('direction', $direction);

        return $next($request);
    }
}
