@php
    use App\Services\Purchasing\LocalizedMoney;
    $field = 'mt-1 block min-h-11 w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500';
@endphp
<section dir="{{ $textDirection }}" lang="{{ $locale }}" aria-labelledby="network-title" class="space-y-4 text-start">
    <div>
        <h1 id="network-title" class="text-xl font-semibold text-gray-900">{{ __('network.discovery.title') }}</h1>
        <p class="text-sm text-gray-600">{{ __('network.discovery.help') }}</p>
    </div>

    <form wire:submit="applyFilters" role="search" class="flex flex-wrap items-end gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
        <div class="min-w-0 flex-1 basis-60">
            <label for="network-search" class="block text-sm font-medium text-gray-800">{{ __('network.discovery.search') }}</label>
            <input id="network-search" type="search" wire:model="search" maxlength="{{ \App\Actions\Network\SharedListings::MAX_SEARCH_LENGTH }}" autocomplete="off" placeholder="{{ __('network.discovery.search_placeholder') }}" class="{{ $field }}">
        </div>
        <div class="basis-40">
            <label for="network-condition" class="block text-sm font-medium text-gray-800">{{ __('network.fields.condition') }}</label>
            <select id="network-condition" wire:model.live="condition" class="{{ $field }}">
                <option value="">{{ __('network.discovery.any') }}</option>
                @foreach ($conditions as $option)
                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="basis-48">
            <label for="network-branch" class="block text-sm font-medium text-gray-800">{{ __('network.fields.branch') }}</label>
            <select id="network-branch" wire:model.live="owningStore" class="{{ $field }}">
                <option value="">{{ __('network.discovery.any_branch') }}</option>
                @foreach ($otherStores as $other)
                    <option value="{{ $other->id }}">{{ $other->name }} ({{ $other->code }})</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="inline-flex min-h-11 items-center rounded-lg bg-blue-600 px-4 text-sm font-semibold text-white hover:bg-blue-700">{{ __('network.discovery.apply') }}</button>
        @if ($filtered)
            <button type="button" wire:click="clearFilters" class="inline-flex min-h-11 items-center rounded-lg px-4 text-sm font-semibold text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50">{{ __('network.discovery.clear') }}</button>
        @endif
    </form>

    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200" wire:loading.class="opacity-60" data-testid="network-listings">
        @if ($listings->isEmpty())
            <p class="px-4 py-8 text-center text-sm text-gray-600">{{ $filtered ? __('network.discovery.empty_filtered') : __('network.discovery.empty') }}</p>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach ($listings as $listing)
                    <li wire:key="listing-{{ $listing['key'] }}" class="flex min-h-14 flex-wrap items-center gap-x-4 gap-y-1 px-4 py-3">
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-gray-900">{{ $listing['product'] }}</p>
                            @if ($listing['model'])
                                <p class="text-sm text-gray-600"><bdi>{{ $listing['model'] }}</bdi></p>
                            @endif
                        </div>
                        <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800">
                            <span class="sr-only">{{ __('network.fields.condition') }}:</span> {{ $listing['condition']->label() }}
                        </span>
                        <span class="text-sm text-gray-700">
                            <span class="sr-only">{{ __('network.fields.branch') }}:</span>
                            {{ $listing['store_name'] }} <bdi dir="ltr" class="font-mono text-gray-500">{{ $listing['store_code'] }}</bdi>
                        </span>
                        <span dir="ltr" class="ms-auto font-semibold tabular-nums text-gray-900">
                            <span class="sr-only">{{ __('network.fields.price') }}:</span> {{ LocalizedMoney::format($listing['price_minor']) }} TRY
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{ $listings->links() }}
</section>
