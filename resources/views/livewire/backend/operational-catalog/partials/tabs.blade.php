<nav aria-label="{{ __('catalog.navigation') }}" class="flex gap-1 overflow-x-auto rounded-lg bg-gray-100 p-1 text-sm font-medium">
    @foreach ([
        'subdomain.catalog.definitions' => __('catalog.tabs.definitions'),
        'subdomain.catalog.settings' => __('catalog.tabs.settings'),
    ] as $routeName => $label)
        <a
            href="{{ route($routeName) }}"
            @if (request()->routeIs($routeName)) aria-current="page" @endif
            @class([
                'whitespace-nowrap rounded-md px-4 py-2',
                'bg-white text-gray-900 shadow-sm' => request()->routeIs($routeName),
                'text-gray-600 hover:text-gray-900' => ! request()->routeIs($routeName),
            ])
        >{{ $label }}</a>
    @endforeach
</nav>
