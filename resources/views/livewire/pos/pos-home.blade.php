<section dir="{{ $textDirection }}" lang="{{ $locale }}" aria-labelledby="pos-home-title" class="mx-auto max-w-2xl text-start">
    <h1 id="pos-home-title" class="text-xl font-semibold text-gray-900">{{ __('pos.home.title') }}</h1>

    @if ($stores->isEmpty())
        <p class="mt-4 rounded-xl bg-white p-5 text-sm text-gray-700 shadow-sm ring-1 ring-gray-200">{{ __('pos.home.no_access') }}</p>
    @else
        <p class="mt-1 text-sm text-gray-600">{{ __('pos.home.help') }}</p>
        <ul class="mt-4 grid gap-3 sm:grid-cols-2">
            @foreach ($stores as $store)
                <li wire:key="pos-store-{{ $store->id }}">
                    <a href="{{ route('pos.counter', $store) }}" class="flex min-h-16 flex-col justify-center rounded-xl bg-white px-5 py-4 shadow-sm ring-1 ring-gray-200 hover:ring-blue-400 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">
                        <span class="font-mono text-sm font-semibold text-gray-500" dir="ltr">{{ $store->code }}</span>
                        <span class="text-base font-semibold text-gray-900">{{ $store->name }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</section>
