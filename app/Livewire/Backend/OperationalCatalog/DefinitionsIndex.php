<?php

declare(strict_types=1);

namespace App\Livewire\Backend\OperationalCatalog;

use App\Actions\Catalog\SaveProductDefinition;
use App\Enums\CatalogIdentifierType;
use App\Enums\TrackingMode;
use App\Livewire\Backend\Concerns\UsesOperationalLocale;
use App\Models\CatalogBrand;
use App\Models\CatalogCategory;
use App\Models\ProductDefinition;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class DefinitionsIndex extends Component
{
    use UsesOperationalLocale;
    use WithPagination;

    public const SEARCH_MODES = ['name', 'sku', 'barcode'];

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: 'name')]
    public string $searchBy = 'name';

    public bool $formOpen = false;

    #[Locked]
    public ?int $editingDefinitionId = null;

    public string $name = '';

    public string $model = '';

    public string $description = '';

    public string $trackingMode = 'quantity';

    public string $sku = '';

    public string $barcode = '';

    public ?int $brandId = null;

    public ?int $categoryId = null;

    public bool $isActive = true;

    public function mount(): void
    {
        $this->authorize('viewAny', ProductDefinition::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSearchBy(): void
    {
        $this->resetPage();
    }

    public function openCreateForm(): void
    {
        $this->authorize('create', ProductDefinition::class);

        $this->resetForm();
        $this->formOpen = true;
    }

    public function openEditForm(int $definitionId): void
    {
        $definition = ProductDefinition::query()->findOrFail($definitionId);
        $this->authorize('update', $definition);

        $this->resetForm();
        $this->editingDefinitionId = $definition->id;
        $this->name = $definition->name;
        $this->model = (string) $definition->model;
        $this->description = (string) $definition->description;
        $this->trackingMode = $definition->tracking_mode->value;
        $this->sku = (string) $definition->sku;
        $this->barcode = (string) $definition->barcode;
        $this->brandId = $definition->catalog_brand_id;
        $this->categoryId = $definition->catalog_category_id;
        $this->isActive = $definition->is_active;
        $this->formOpen = true;
    }

    public function closeForm(): void
    {
        $this->resetForm();
        $this->formOpen = false;
    }

    public function save(SaveProductDefinition $saveProductDefinition): void
    {
        $definition = $this->editingDefinitionId !== null
            ? ProductDefinition::query()->findOrFail($this->editingDefinitionId)
            : null;

        $saveProductDefinition(auth()->user(), $definition, [
            'name' => $this->name,
            'model' => $this->model,
            'description' => $this->description,
            'tracking_mode' => $this->trackingMode,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'catalog_brand_id' => $this->brandId,
            'catalog_category_id' => $this->categoryId,
            'is_active' => $this->isActive,
        ]);

        $this->closeForm();
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => $definition ? __('catalog.messages.definition_updated') : __('catalog.messages.definition_created'),
        ]);
    }

    private function resetForm(): void
    {
        $this->reset([
            'editingDefinitionId', 'name', 'model', 'description', 'trackingMode',
            'sku', 'barcode', 'brandId', 'categoryId', 'isActive',
        ]);
        $this->resetErrorBag();
    }

    /**
     * @return Collection<int, CatalogBrand>
     */
    private function brandOptions(): Collection
    {
        return CatalogBrand::query()
            ->where(fn ($query) => $query->where('is_active', true)->when($this->brandId, fn ($q) => $q->orWhereKey($this->brandId)))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * @return Collection<int, CatalogCategory>
     */
    private function categoryOptions(): Collection
    {
        return CatalogCategory::query()
            ->where(fn ($query) => $query->where('is_active', true)->when($this->categoryId, fn ($q) => $q->orWhereKey($this->categoryId)))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    #[Layout('layouts.backend')]
    public function render(): View
    {
        $this->authorize('viewAny', ProductDefinition::class);

        $searchBy = in_array($this->searchBy, self::SEARCH_MODES, true) ? $this->searchBy : 'name';
        $identifier = ProductDefinition::normalizeIdentifier($this->search);

        $definitions = ProductDefinition::query()
            ->with(['brand:id,name', 'category:id,name'])
            ->when($searchBy === 'name', fn ($query) => $query->search($this->search))
            ->when($searchBy !== 'name' && $identifier !== null, fn ($query) => $query->withIdentifier(CatalogIdentifierType::from($searchBy), $identifier))
            ->orderBy('name')
            ->paginate(25);

        $editing = $this->editingDefinitionId !== null
            ? ProductDefinition::query()->find($this->editingDefinitionId)
            : null;

        return view('livewire.backend.operational-catalog.definitions-index', [
            'definitions' => $definitions,
            'trackingModes' => TrackingMode::cases(),
            'brands' => $this->formOpen ? $this->brandOptions() : collect(),
            'categories' => $this->formOpen ? $this->categoryOptions() : collect(),
            'trackingModeLocked' => $editing?->hasInventoryHistory() ?? false,
            'isFiltering' => $identifier !== null,
            ...$this->localeViewData(),
        ])->title(__('catalog.title'));
    }
}
