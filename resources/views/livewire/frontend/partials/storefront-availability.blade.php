{{-- Linked branch stock is display-only for now: availability replaces the add-to-cart button. --}}
<div class="mt-auto pt-4" data-testid="storefront-availability">
    @if ($available > 0)
        <p class="w-full rounded-xl bg-green-50 py-1.5 text-center text-sm font-semibold text-green-800 ring-1 ring-green-200">{{ trans_choice('main.storefront_in_stock', $available, ['count' => $available]) }}</p>
    @else
        <p class="w-full rounded-xl bg-gray-100 py-1.5 text-center text-sm font-semibold text-gray-700 ring-1 ring-gray-200">{{ __('main.storefront_out_of_stock') }}</p>
    @endif
    <p class="mt-1 text-center text-xs text-gray-600">{{ __('main.storefront_in_store_only') }}</p>
</div>
