@php
    $iconMap = [
        'currency-dollar' => [
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>',
            'bg' => 'bg-green-500/90',
            'text' => 'text-white',
        ],
        'clipboard-list' => [
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" /></svg>',
            'bg' => 'bg-blue-600/90',
            'text' => 'text-white',
        ],
        'star' => [
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.75.75 0 0 1 1.04 0l2.09 2.122 2.91.423a.75.75 0 0 1 .416 1.28l-2.104 2.05.497 2.899a.75.75 0 0 1-1.088.791L12 12.347l-2.6 1.367a.75.75 0 0 1-1.088-.79l.497-2.9-2.104-2.05a.75.75 0 0 1 .416-1.28l2.91-.423 2.09-2.122Z" /></svg>',
            'bg' => 'bg-orange-400/90',
            'text' => 'text-white',
        ],
        'truck' => [
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" /></svg>',
            'bg' => 'bg-purple-600/90',
            'text' => 'text-white',
        ],
    ];
@endphp
<div class="bg-white rounded-2xl shadow-md p-6 flex flex-col justify-between min-h-[180px] relative transition-all duration-300 hover:scale-105 hover:shadow-xl">
    <div class="flex justify-between items-center">
        <div class="shrink-0 rounded-xl p-3 {{ $iconMap[$icon]['bg'] ?? 'bg-gray-200' }} {{ $iconMap[$icon]['text'] ?? 'text-gray-700' }} shadow-md">
            {!! $iconMap[$icon]['icon'] ?? '' !!}
        </div>
        @if($trend)
            {!! $trend !!}
        @endif
        @if(isset($active))
            <span class="ml-2 px-3 py-1 rounded-full text-sm font-semibold bg-blue-100 text-blue-700">{{ $active }}</span>
        @endif
        @if(isset($label))
            <span class="ml-2 px-3 py-1 rounded-full text-sm font-semibold bg-green-100 text-green-700">{{ $label }}</span>
        @endif
    </div>
    <div class="mt-4">
        <div class="text-sm text-gray-600 font-medium mb-1">{{ $title }}</div>
        <div class="text-2xl font-bold text-gray-900">{{ $value }}</div>
        <div class="text-sm text-gray-500 mt-1">
            {{ $slot }}
        </div>
    </div>
</div>
