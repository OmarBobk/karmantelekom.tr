<?php

declare(strict_types=1);

namespace App\Livewire\Backend\OperationalCatalog;

use App\Livewire\Backend\Concerns\UsesOperationalLocale;
use App\Models\CatalogBrand;
use App\Models\CatalogCategory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Brands and flat categories for the operational catalog.
 */
class CatalogSettings extends Component
{
    use UsesOperationalLocale;

    /** @var array<string, class-string<Model>> */
    private const KINDS = [
        'brand' => CatalogBrand::class,
        'category' => CatalogCategory::class,
    ];

    public string $newBrandName = '';

    public string $newCategoryName = '';

    #[Locked]
    public ?string $editingKind = null;

    #[Locked]
    public ?int $editingId = null;

    public string $editingName = '';

    public function mount(): void
    {
        $this->authorize('viewAny', CatalogBrand::class);
        $this->authorize('viewAny', CatalogCategory::class);
    }

    public function create(string $kind): void
    {
        $modelClass = $this->modelClass($kind);
        $this->authorize('create', $modelClass);

        $field = $kind === 'brand' ? 'newBrandName' : 'newCategoryName';
        $this->{$field} = Str::trim($this->{$field});

        $validated = $this->validate(
            [$field => ['required', 'string', 'max:255', Rule::unique((new $modelClass)->getTable(), 'name')]],
            attributes: [$field => __('catalog.fields.name')],
        );

        $modelClass::query()->create(['name' => $validated[$field]]);

        $this->reset($field);
        $this->dispatch('notify', ['type' => 'success', 'message' => __("catalog.messages.{$kind}_created")]);
    }

    public function edit(string $kind, int $id): void
    {
        $record = $this->modelClass($kind)::query()->findOrFail($id);
        $this->authorize('update', $record);

        $this->resetErrorBag();
        $this->editingKind = $kind;
        $this->editingId = $record->getKey();
        $this->editingName = $record->getAttribute('name');
    }

    public function cancelEdit(): void
    {
        $this->reset(['editingKind', 'editingId', 'editingName']);
        $this->resetErrorBag();
    }

    public function update(): void
    {
        if ($this->editingKind === null || $this->editingId === null) {
            return;
        }

        $modelClass = $this->modelClass($this->editingKind);
        $record = $modelClass::query()->findOrFail($this->editingId);
        $this->authorize('update', $record);

        $this->editingName = Str::trim($this->editingName);

        $validated = $this->validate(
            ['editingName' => ['required', 'string', 'max:255', Rule::unique($record->getTable(), 'name')->ignore($record->getKey())]],
            attributes: ['editingName' => __('catalog.fields.name')],
        );

        $record->update(['name' => $validated['editingName']]);

        $kind = $this->editingKind;
        $this->cancelEdit();
        $this->dispatch('notify', ['type' => 'success', 'message' => __("catalog.messages.{$kind}_updated")]);
    }

    public function toggleActive(string $kind, int $id): void
    {
        $record = $this->modelClass($kind)::query()->findOrFail($id);
        $this->authorize('update', $record);

        $record->update(['is_active' => ! $record->getAttribute('is_active')]);

        $this->dispatch('notify', ['type' => 'success', 'message' => __("catalog.messages.{$kind}_updated")]);
    }

    /**
     * @return class-string<Model>
     */
    private function modelClass(string $kind): string
    {
        return self::KINDS[$kind] ?? abort(404);
    }

    #[Layout('layouts.backend')]
    public function render(): View
    {
        $this->authorize('viewAny', CatalogBrand::class);
        $this->authorize('viewAny', CatalogCategory::class);

        return view('livewire.backend.operational-catalog.catalog-settings', [
            'brands' => CatalogBrand::query()->withCount('productDefinitions')->orderBy('name')->get(),
            'categories' => CatalogCategory::query()->withCount('productDefinitions')->orderBy('name')->get(),
            ...$this->localeViewData(),
        ])->title(__('catalog.tabs.settings'));
    }
}
