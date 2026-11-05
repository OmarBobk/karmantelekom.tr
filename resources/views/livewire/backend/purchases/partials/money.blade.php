{{-- Expects: $minor (int), $currency (Currency|string); optional $class --}}
<span dir="ltr" class="inline-block whitespace-nowrap tabular-nums {{ $class ?? '' }}">{{ \App\Services\Purchasing\Money::format($minor) }}&nbsp;<span class="text-[0.8em] font-medium text-gray-500">{{ $currency instanceof \App\Enums\Currency ? $currency->value : $currency }}</span></span>
