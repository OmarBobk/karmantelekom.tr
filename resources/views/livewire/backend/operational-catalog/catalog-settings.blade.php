<div dir="{{ $textDirection }}" lang="{{ $locale }}" class="mx-auto max-w-6xl space-y-6 text-start">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ __('catalog.title') }}</h1>
        <p class="mt-1 text-sm text-gray-500">{{ __('catalog.settings.subtitle') }}</p>
    </div>

    @include('livewire.backend.operational-catalog.partials.tabs')

    <div class="grid gap-6 md:grid-cols-2">
        @include('livewire.backend.operational-catalog.partials.taxonomy-panel', ['kind' => 'brand', 'records' => $brands, 'newField' => 'newBrandName'])
        @include('livewire.backend.operational-catalog.partials.taxonomy-panel', ['kind' => 'category', 'records' => $categories, 'newField' => 'newCategoryName'])
    </div>
</div>
