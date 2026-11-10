<?php

declare(strict_types=1);

namespace App\Livewire\Backend\Storefront;

use App\Actions\Storefront\CreateStorefrontProduct;
use App\Actions\Storefront\LinkStorefrontProduct;
use App\Actions\Storefront\SetStorefrontChannel;
use App\Actions\Storefront\StorefrontAvailability;
use App\Actions\Storefront\UnlinkStorefrontProduct;
use App\Enums\StorefrontChannel;
use App\Livewire\Backend\Concerns\UsesOperationalLocale;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductDefinition;
use App\Models\StorefrontAllocation;
use App\Models\StorefrontListing;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The publisher's single screen: every product a branch made eligible for the storefront, its
 * branch allocations and live availability, its explicit storefront link, and publication per
 * channel. Prices are shown read-only; they stay with the legacy product admin.
 */
class StorefrontPublisher extends Component
{
    use UsesOperationalLocale;
    use WithPagination;

    public const PER_PAGE = 20;

    public const MAX_SEARCH_LENGTH = 80;

    public string $search = '';

    /**
     * The product whose link panel is open.
     */
    #[Locked]
    public ?int $linkingDefinitionId = null;

    public string $productSearch = '';

    public ?int $categoryId = null;

    public function mount(): void
    {
        $this->authorize('viewAny', StorefrontListing::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function openLink(int $definitionId): void
    {
        $this->authorize('publish', StorefrontListing::class);
        $this->linkingDefinitionId = $definitionId;
        $this->reset('productSearch', 'categoryId');
        $this->resetErrorBag();
    }

    public function closeLink(): void
    {
        $this->reset('linkingDefinitionId', 'productSearch', 'categoryId');
        $this->resetErrorBag();
    }

    public function link(int $productId, LinkStorefrontProduct $linkStorefrontProduct): void
    {
        $this->authorize('publish', StorefrontListing::class);

        if ($this->linkingDefinitionId === null) {
            return;
        }

        $linkStorefrontProduct(auth()->user(), $this->linkingDefinitionId, $productId);
        $this->closeLink();
        $this->dispatch('notify', ['type' => 'success', 'message' => __('storefront.publisher.linked')]);
    }

    public function createProduct(CreateStorefrontProduct $createStorefrontProduct): void
    {
        $this->authorize('publish', StorefrontListing::class);

        if ($this->linkingDefinitionId === null) {
            return;
        }

        $createStorefrontProduct(auth()->user(), $this->linkingDefinitionId, $this->categoryId);
        $this->closeLink();
        $this->dispatch('notify', ['type' => 'success', 'message' => __('storefront.publisher.created')]);
    }

    public function setChannel(int $listingId, string $channel, bool $publish, SetStorefrontChannel $setStorefrontChannel): void
    {
        $this->authorize('publish', StorefrontListing::class);
        $this->resetErrorBag('publish');

        $setStorefrontChannel(auth()->user(), $listingId, StorefrontChannel::from($channel), $publish);
        $this->dispatch('notify', ['type' => 'success', 'message' => $publish ? __('storefront.publisher.published') : __('storefront.publisher.withdrawn')]);
    }

    public function unlink(int $listingId, UnlinkStorefrontProduct $unlinkStorefrontProduct): void
    {
        $this->authorize('publish', StorefrontListing::class);

        $unlinkStorefrontProduct(auth()->user(), $listingId);
        $this->dispatch('notify', ['type' => 'success', 'message' => __('storefront.publisher.unlinked')]);
    }

    #[Layout('layouts.backend')]
    public function render(): View
    {
        $this->authorize('viewAny', StorefrontListing::class);

        $term = mb_substr(Str::trim($this->search), 0, self::MAX_SEARCH_LENGTH);

        $definitions = ProductDefinition::query()
            ->where(fn (Builder $reviewable) => $reviewable
                ->whereIn('id', StorefrontAllocation::query()->where('quantity', '>', 0)->select('product_definition_id'))
                ->orWhereIn('id', StorefrontListing::query()->select('product_definition_id')))
            ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $match) => $match
                ->where('name', 'like', $this->like($term))
                ->orWhere('model', 'like', $this->like($term))
                ->orWhere('sku', 'like', $this->like($term))))
            ->with(['storefrontListing.product' => fn ($product) => $product
                ->select(['id', 'name', 'code', 'slug', 'is_active'])
                ->withExists(['priceTiers as has_wholesale_tiers' => fn (Builder $tiers) => $tiers->where('is_active', true)])])
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(self::PER_PAGE, ['id', 'name', 'model', 'sku', 'tracking_mode', 'is_active']);

        $definitionIds = $definitions->getCollection()->pluck('id')->all();

        return view('livewire.backend.storefront.storefront-publisher', [
            'definitions' => $definitions,
            'allocations' => $this->allocationsByDefinition($definitionIds),
            'availability' => app(StorefrontAvailability::class)->forDefinitions($definitionIds),
            'channels' => StorefrontChannel::cases(),
            'linkingDefinition' => $this->linkingDefinitionId !== null ? ProductDefinition::query()->find($this->linkingDefinitionId, ['id', 'name', 'model', 'sku']) : null,
            'candidates' => $this->candidates(),
            'categories' => $this->linkingDefinitionId !== null ? Category::query()->orderBy('name')->get(['id', 'name']) : collect(),
            ...$this->localeViewData(),
        ])->title(__('storefront.publisher.title'));
    }

    /**
     * Branch allocations per product: online quantity for quantity products, eligible devices otherwise.
     *
     * @param  list<int>  $definitionIds
     * @return Collection<int, Collection<int, array{store: string, quantity: int}>>
     */
    private function allocationsByDefinition(array $definitionIds): Collection
    {
        return StorefrontAllocation::query()
            ->join('stores', 'stores.id', '=', 'storefront_allocations.store_id')
            ->whereIn('storefront_allocations.product_definition_id', $definitionIds)
            ->where('storefront_allocations.quantity', '>', 0)
            ->groupBy('storefront_allocations.product_definition_id', 'stores.code')
            ->orderBy('stores.code')
            ->selectRaw('storefront_allocations.product_definition_id as definition_id, stores.code as store_code, sum(storefront_allocations.quantity) as allocated')
            ->get()
            ->groupBy('definition_id')
            ->map(fn (Collection $rows): Collection => $rows->map(fn ($row): array => ['store' => (string) $row->store_code, 'quantity' => (int) $row->allocated])->values());
    }

    /**
     * Unlinked storefront products matching the publisher's search, for the open link panel.
     *
     * @return Collection<int, Product>
     */
    private function candidates(): Collection
    {
        $term = mb_substr(Str::trim($this->productSearch), 0, self::MAX_SEARCH_LENGTH);

        if ($this->linkingDefinitionId === null || mb_strlen($term) < 2) {
            return collect();
        }

        return Product::query()
            ->whereDoesntHave('storefrontListing')
            ->where(fn (Builder $match) => $match
                ->where('name', 'like', $this->like($term))
                ->orWhere('tr_name', 'like', $this->like($term))
                ->orWhere('ar_name', 'like', $this->like($term))
                ->orWhere('code', 'like', $this->like($term)))
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'code', 'slug', 'is_active']);
    }

    private function like(string $term): string
    {
        return '%'.addcslashes($term, '%_\\').'%';
    }
}
