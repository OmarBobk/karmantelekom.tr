<?php

declare(strict_types=1);

namespace App\Livewire\Frontend;

use App\Services\LanguageService;
use Livewire\Attributes\Layout;
use Livewire\Component;

class PrivacyPolicyComponent extends Component
{
    public string $currentDirection = 'ltr';
    private LanguageService $languageService;

    public function boot(LanguageService $languageService): void
    {
        $this->languageService = $languageService;
    }

    public function mount(): void
    {
        $this->currentDirection = $this->languageService->getCurrentDirection();
    }

    #[Layout('layouts.frontend')]
    public function render()
    {
        return view('livewire.frontend.privacy-policy-component');
    }
}

