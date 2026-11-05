{{-- Expects: $items (list of [label, url|null]) --}}
<nav aria-label="{{ __('inventory.breadcrumbs') }}">
    <ol class="flex flex-wrap items-center gap-1 text-sm text-gray-500">
        @foreach ($items as $index => [$label, $url])
            @if ($index > 0)
                <li aria-hidden="true" class="text-gray-300">/</li>
            @endif
            <li>
                @if ($url)
                    <a href="{{ $url }}" class="inline-flex min-h-11 items-center hover:text-gray-900"><bdi>{{ $label }}</bdi></a>
                @else
                    <span aria-current="page" class="font-medium text-gray-900"><bdi>{{ $label }}</bdi></span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
