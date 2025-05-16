<?php

declare(strict_types=1);

namespace App\Livewire\Frontend\Partials;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;
use App\Services\LanguageService;
use App\Exceptions\LanguageNotSupportedException;

class HeaderComponent extends Component
{
    public string $cartCount = '1';
    public string $currentLanguage = 'EN';
    public string $searchComponentKey;
    public string $currentDirection = 'ltr';
    private LanguageService $languageService;

    public function boot(LanguageService $languageService): void
    {
        $this->languageService = $languageService;
    }

    public function mount(): void
    {
        $this->currentLanguage = $this->languageService->getCurrentLanguage();
        $this->currentDirection = $this->languageService->getCurrentDirection();
        $this->searchComponentKey = 'search-' . uniqid();
    }

    public function render()
    {
        return view('livewire.frontend.partials.header-component');
    }

    public function changeLanguage(string $code): void
    {
        try {
            $this->languageService->switchLanguage($code);
            
            $this->currentLanguage = $this->languageService->getCurrentLanguage();
            $this->currentDirection = $this->languageService->getCurrentDirection();
            
            // Force a re-render of the search component
            $this->searchComponentKey = 'search-' . uniqid();

            // Dispatch event with language metadata
            $this->dispatch('languageChanged', [
                'code' => $this->currentLanguage,
                'direction' => $this->currentDirection,
                'locale' => strtolower($this->currentLanguage)
            ]);

        } catch (LanguageNotSupportedException $e) {
            logger()->error('Language switch failed: ' . $e->getMessage());
            $this->dispatch('languageError', ['message' => $e->getMessage()]);
        }
    }

    public function getLanguageName(string $code): string
    {
        return $this->languageService->getLanguageName($code);
    }

    public function getLanguageFlag(string $code): string
    {
        return Storage::url($this->languageService->getLanguageFlag($code));
    }
}
