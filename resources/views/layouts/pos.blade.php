@php
    $posLocale = \App\Livewire\Backend\OperationalLocale::resolve();
    $posDirection = \App\Livewire\Backend\OperationalLocale::direction();
    $posStore = $posStore ?? null;
@endphp
<!DOCTYPE html>
<html lang="{{ $posLocale }}" dir="{{ $posDirection }}">
    <head>
        <meta charset="utf-8">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="icon" type="image/png" href="{{ asset('assets/images/karmantelekom_logo.png') }}">
        <title>{{ $title ?? __('pos.title') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>[x-cloak] { display: none !important; }</style>
    </head>
    <body class="min-h-screen bg-gray-100 text-gray-900">
        <div
            x-data="{
                notifications: [],
                add(detail) {
                    const message = Array.isArray(detail) ? detail[0] : detail;
                    if (! message || ! message.message) { return; }
                    const id = Date.now() + Math.random();
                    this.notifications.push({ id, type: message.type ?? 'info', message: message.message });
                    setTimeout(() => this.notifications = this.notifications.filter(n => n.id !== id), message.sec ?? 4000);
                },
            }"
            x-on:notify.window="add($event.detail)"
            aria-live="polite"
            class="pointer-events-none fixed inset-x-0 top-3 z-50 flex flex-col items-center gap-2 px-3"
        >
            <template x-for="notification in notifications" :key="notification.id">
                <p
                    dir="auto"
                    class="pointer-events-auto w-full max-w-md rounded-lg px-4 py-3 text-sm font-medium shadow-lg"
                    :class="{
                        'bg-emerald-700 text-white': notification.type === 'success',
                        'bg-red-700 text-white': notification.type === 'error',
                        'bg-amber-100 text-amber-900 ring-1 ring-amber-300': notification.type === 'warning',
                        'bg-gray-900 text-white': notification.type === 'info',
                    }"
                    x-text="notification.message"
                ></p>
            </template>
        </div>

        <header class="border-b border-gray-200 bg-white print:hidden">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-x-4 gap-y-2 px-4 py-2">
                <a href="{{ route('pos.home') }}" class="flex min-h-11 items-center gap-2 text-base font-semibold text-gray-900">
                    <img src="{{ asset('assets/images/karmantelekom_logo.png') }}" class="h-7 w-auto" alt="">
                    <span>{{ __('pos.title') }}</span>
                </a>

                @if ($posStore)
                    <span class="rounded-md bg-gray-100 px-2 py-1 text-sm font-medium text-gray-700">
                        <bdi dir="ltr" class="font-mono">{{ $posStore->code }}</bdi> · {{ $posStore->name }}
                    </span>
                    <nav aria-label="{{ __('pos.nav.label') }}" class="flex items-center gap-1">
                        <a href="{{ route('pos.counter', $posStore) }}" @class(['inline-flex min-h-11 items-center rounded-lg px-3 text-sm font-medium', 'bg-blue-50 text-blue-700' => request()->routeIs('pos.counter'), 'text-gray-700 hover:bg-gray-100' => ! request()->routeIs('pos.counter')]) @if (request()->routeIs('pos.counter')) aria-current="page" @endif>{{ __('pos.nav.counter') }}</a>
                        <a href="{{ route('pos.sales', $posStore) }}" @class(['inline-flex min-h-11 items-center rounded-lg px-3 text-sm font-medium', 'bg-blue-50 text-blue-700' => request()->routeIs('pos.sales*'), 'text-gray-700 hover:bg-gray-100' => ! request()->routeIs('pos.sales*')]) @if (request()->routeIs('pos.sales*')) aria-current="page" @endif>{{ __('pos.nav.sales') }}</a>
                    </nav>
                @endif

                <div class="ms-auto flex items-center gap-2">
                    <form method="POST" action="{{ route('pos.locale') }}" class="flex items-center">
                        @csrf
                        <input type="hidden" name="locale" value="{{ $posLocale === 'ar' ? 'en' : 'ar' }}">
                        <button type="submit" class="inline-flex min-h-11 items-center rounded-lg px-3 text-sm font-medium text-gray-700 hover:bg-gray-100" lang="{{ $posLocale === 'ar' ? 'en' : 'ar' }}">
                            {{ $posLocale === 'ar' ? 'English' : 'العربية' }}
                        </button>
                    </form>
                    <span class="hidden text-sm text-gray-600 sm:inline">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="inline-flex min-h-11 items-center rounded-lg px-3 text-sm font-medium text-gray-700 hover:bg-gray-100">{{ __('pos.nav.logout') }}</button>
                    </form>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-7xl p-3 sm:p-4">
            {{ $slot }}
        </main>
    </body>
</html>
