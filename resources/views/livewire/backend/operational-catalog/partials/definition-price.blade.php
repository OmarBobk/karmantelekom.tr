@if ($definition->tracking_mode === \App\Enums\TrackingMode::USED_DEVICE)
    <span class="text-xs text-gray-500">{{ __('pos.pricing.per_unit') }}</span>
@elseif ($definition->sellingPriceMinor() === null)
    <span class="inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-800 ring-1 ring-amber-200">{{ __('pos.pricing.not_priced') }}</span>
@else
    <span dir="ltr" class="font-mono tabular-nums">{{ \App\Services\Purchasing\Money::format($definition->sellingPriceMinor()) }}</span>
@endif
