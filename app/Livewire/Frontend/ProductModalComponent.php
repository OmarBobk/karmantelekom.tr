<?php

namespace App\Livewire\Frontend;

use App\Actions\Storefront\StorefrontAvailability;
use App\Enums\StorefrontChannel;
use App\Facades\Settings;
use App\Models\Product;
use App\Models\StorefrontListing;
use App\Models\WholesaleProduct;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ProductModalComponent extends Component
{
    public ?Product $product = null;

    public bool $showModal = false;

    public ?string $selectedImage = null;

    public ?int $selectedImageId = null;

    public ?string $requestQuoteUrl = null;

    public ?string $moreInfoUrl = null;

    public string $productSlugUrl = '';

    public string $productIdUrl = '';

    protected array $queryString = ['productSlugUrl', 'productIdUrl'];

    protected $listeners = ['openProductModal'];

    /**
     * Units customers can see as available when the product presents linked branch stock, which
     * is display-only; null for legacy products, which keep their add-to-cart button.
     */
    #[Locked]
    public ?int $storefrontAvailable = null;

    public function openProductModal($productId): void
    {
        $this->product = Product::query()->notWithheldFrom(StorefrontChannel::audienceOf(auth()->user()))->find($productId);

        if ($this->product !== null) {
            // Livewire reloads a model's loaded relations on every request, so linked products never
            // carry their tiers; visiblePriceTiers() decides per render who may see them.
            $linked = StorefrontListing::query()->where('product_id', $this->product->id)->exists();
            $this->product->load([
                'category',
                'images',
                'prices',
                ...($linked ? [] : ['priceTiers' => function ($q) {
                    $q->where('is_active', true)
                        ->with('currency')
                        ->orderBy('min_qty');
                }]),
            ]);
        }

        if ($this->product === null) {
            $this->closeModal();
            $this->dispatch('notify', [['type' => 'alert-danger', 'message' => __('main.storefront_product_unavailable'), 'sec' => 4000]]);

            return;
        }

        $this->storefrontAvailable = app(StorefrontAvailability::class)->forProducts([$this->product->id])[$this->product->id] ?? null;

        $this->productSlugUrl = $this->product->slug;
        $this->productIdUrl = $this->product->id;

        $this->showModal = true;
        $this->selectedImage = $this->product?->images->where('is_primary', true)->first()?->image_url
            ?? $this->product?->images->first()?->image_url;
        $this->selectedImageId = $this->product?->images->where('is_primary', true)->first()?->id
            ?? $this->product?->images->first()?->id;

        $requestQuoteMessage = urlencode(__('main.wp_quote_message').' *'.$this->product->name.'* '.__('main.code').': *'.$this->product->code.'*');
        $this->requestQuoteUrl = 'https://wa.me/'.Settings::get('whatsapp_number', '905353402539').'?text='.$requestQuoteMessage;

        $moreInfoMessage = urlencode(__('main.wp_more_info_message').' *'.$this->product->name.'* '.__('main.code').': *'.$this->product->code.'*');
        $this->moreInfoUrl = 'https://wa.me/'.Settings::get('whatsapp_number', '905353402539').'?text='.$moreInfoMessage;
    }

    public function selectImage($imageUrl, $imageId): void
    {
        $this->selectedImage = $imageUrl;
        $this->selectedImageId = $imageId;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->product = null;
        $this->moreInfoUrl = null;
        $this->requestQuoteUrl = null;
        $this->productIdUrl = '';
        $this->productSlugUrl = '';
        $this->storefrontAvailable = null;

        if (session('openProductModalFromDashboard')) {
            session()->forget('openProductModalFromDashboard');
        }
    }

    public function render()
    {
        return view('livewire.frontend.product-modal-component', [
            'priceTiers' => $this->visiblePriceTiers(),
        ]);
    }

    /**
     * Wholesale tiers this viewer may see. A linked product shows them only to a wholesale viewer
     * while it is published to wholesale, and retail viewers get its retail price. Legacy products
     * keep their existing presentation.
     *
     * @return Collection<int, WholesaleProduct>
     */
    private function visiblePriceTiers(): Collection
    {
        if ($this->product === null) {
            return new Collection;
        }

        $listing = StorefrontListing::query()->where('product_id', $this->product->id)->first(['id', 'wholesale_published_at']);

        if ($listing === null) {
            return $this->product->priceTiers;
        }

        if (! $listing->isPublishedTo(StorefrontChannel::WHOLESALE) || ! in_array(StorefrontChannel::WHOLESALE, StorefrontChannel::audienceOf(auth()->user()), true)) {
            return new Collection;
        }

        return $this->product->priceTiers()->where('is_active', true)->with('currency')->get();
    }
}
