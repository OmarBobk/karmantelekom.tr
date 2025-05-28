@props([
    'currentLanguage',
    'position' => 'right', // right or bottom
    'variant' => 'default' // default or sidebar
])

<div class="relative" x-data="{ open: false }">
    <button @click="open = !open"
            @click.away="open = false"
            class="{{ $variant === 'sidebar' ? 'flex items-center justify-between w-full px-4 py-3 text-base font-medium text-gray-900 rounded-xl hover:bg-gray-50 transition-colors duration-200' : 'p-2.5 text-gray-700 rounded-xl transition-all duration-200 h-11 w-11 flex items-center justify-center' }}"
            aria-expanded="false">
        <div class="flex items-center gap-2">
            <div class="w-8 h-6 flex">
                <img src="{{ $this->getLanguageFlag($currentLanguage) }}" alt="{{ $this->getLanguageName($currentLanguage) }}">
            </div>
            @if($variant === 'sidebar')
                <span>{{ __('main.' . strtolower($this->getLanguageName($currentLanguage)) ) }}</span>
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            @endif
        </div>
    </button>

    <div x-show="open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="absolute {{ $position === 'bottom' ? 'bottom-full mb-2' : 'mt-2' }} {{ app()->getLocale() === 'ar' ? 'left-0' : 'right-0' }} w-48 rounded-xl bg-white shadow-lg ring-1 ring-black ring-opacity-5 z-50"
         x-cloak>
        <div class="py-1">
            <button wire:click="changeLanguage('en')"
                    class="flex gap-2 hover:gap-4 items-center w-full px-4 py-2 text-sm {{ app()->getLocale() === 'ar' ? 'text-right' : 'text-left' }} text-gray-700 hover:bg-gray-100"
                    :class="{ 'bg-gray-50': '{{ $currentLanguage }}' === 'en' }">
                <div class="h-6 flex">
                    <img src="{{ $this->getLanguageFlag('en') }}" alt="English">
                </div>
                <span>{{__('main.english')}}</span>
                @if($currentLanguage === 'en')
                    <svg class="{{ app()->getLocale() === 'ar' ? 'mr-auto' : 'ml-auto' }} h-5 w-5 text-emerald-600" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    </svg>
                @endif
            </button>
            <button wire:click="changeLanguage('tr')"
                    class="flex gap-2 hover:gap-4 items-center w-full px-4 py-2 text-sm {{ app()->getLocale() === 'ar' ? 'text-right' : 'text-left' }} text-gray-700 hover:bg-gray-100"
                    :class="{ 'bg-gray-50': '{{ $currentLanguage }}' === 'tr' }">
                <div class="h-6 flex">
                    <img src="{{ $this->getLanguageFlag('tr') }}" alt="Turkish">
                </div>
                <span>{{__('main.turkish')}}</span>
                @if($currentLanguage === 'tr')
                    <svg class="{{ app()->getLocale() === 'ar' ? 'mr-auto' : 'ml-auto' }} h-5 w-5 text-emerald-600" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    </svg>
                @endif
            </button>
            <button wire:click="changeLanguage('ar')"
                    class="flex gap-2 hover:gap-4 items-center w-full px-4 py-2 text-sm {{ app()->getLocale() === 'ar' ? 'text-right' : 'text-left' }} text-gray-700 hover:bg-gray-100"
                    :class="{ 'bg-gray-50': '{{ $currentLanguage }}' === 'ar' }">
                <div class="h-6 flex">
                    <img src="{{ $this->getLanguageFlag('ar') }}" alt="Arabic">
                </div>
                <span>{{__('main.arabic')}}</span>
                @if($currentLanguage === 'ar')
                    <svg class="{{ app()->getLocale() === 'ar' ? 'mr-auto' : 'ml-auto' }} h-5 w-5 text-emerald-600" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    </svg>
                @endif
            </button>
        </div>
    </div>
</div> 