<?php

namespace App\Livewire;

use App\Facades\Settings;
use App\Models\Product;
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

    public function openProductModal($productId): void
    {

        $this->product = Product::with(['category', 'images', 'prices'])
            ->find($productId);

        $this->productSlugUrl = $this->product->slug;
        $this->productIdUrl = $this->product->id;

        $this->showModal = true;
        $this->selectedImage = $this->product?->images->where('is_primary', true)->first()?->image_url
            ?? $this->product?->images->first()?->image_url;
        $this->selectedImageId = $this->product?->images->where('is_primary', true)->first()?->id
            ?? $this->product?->images->first()?->id;

        $requestQuoteMessage = urlencode(__('main.wp_quote_message') .' *' . $this->product->name . '* '. __('main.code') .': *' . $this->product->code . '*');
        $this->requestQuoteUrl = 'https://wa.me/' . Settings::get('whatsapp_number', '905353402539') . '?text=' . $requestQuoteMessage;

        $moreInfoMessage = urlencode(__('main.wp_more_info_message') . ' *' . $this->product->name . '* ' . __('main.code') . ': *' . $this->product->code . '*');
        $this->moreInfoUrl = 'https://wa.me/' . Settings::get('whatsapp_number', '905353402539') . '?text=' . $moreInfoMessage;
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

        if (session('openProductModalFromDashboard')) {
            session()->forget('openProductModalFromDashboard');
        }
    }

    public function render()
    {
        return view('livewire.product-modal-component');
    }
}
