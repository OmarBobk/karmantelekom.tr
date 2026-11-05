<div dir="{{ $textDirection }}" lang="{{ $locale }}" class="mx-auto max-w-6xl space-y-6 text-start">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ __('inventory.title') }}</h1>
        <p class="mt-1 text-sm text-gray-500">{{ __('inventory.stores.subtitle') }}</p>
    </div>

    @if ($stores->isEmpty())
        <div class="rounded-xl border-2 border-dashed border-gray-300 bg-white px-6 py-12 text-center">
            <h2 class="text-base font-semibold text-gray-900">{{ __('inventory.stores.empty_title') }}</h2>
            <p class="mt-1 text-sm text-gray-500">{{ __('inventory.stores.empty_body') }}</p>
        </div>
    @else
        <livewire:backend.inventory.device-lookup />

        <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($stores as $store)
                <li wire:key="inventory-store-{{ $store->id }}">
                    <a href="{{ route('subdomain.inventory.store', $store) }}" class="block h-full rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200 transition hover:ring-blue-300 focus:outline-hidden focus:ring-2 focus:ring-blue-500">
                        <div class="flex items-start justify-between gap-3">
                            <p class="font-semibold text-gray-900">{{ $store->name }}</p>
                            <span dir="ltr" class="rounded-md bg-gray-100 px-2 py-0.5 font-mono text-xs text-gray-700">{{ $store->code }}</span>
                        </div>
                        <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <dt class="text-xs text-gray-500">{{ __('inventory.stores.quantity_on_hand') }}</dt>
                                <dd dir="ltr" class="text-start font-mono text-lg font-semibold tabular-nums text-gray-900">{{ (int) $store->quantity_on_hand }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500">{{ __('inventory.stores.available_units') }}</dt>
                                <dd dir="ltr" class="text-start font-mono text-lg font-semibold tabular-nums text-gray-900">{{ $store->available_units_count }}</dd>
                            </div>
                        </dl>
                        <p class="mt-4 text-sm font-medium text-blue-600">{{ __('inventory.actions.open_store') }}</p>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</div>
