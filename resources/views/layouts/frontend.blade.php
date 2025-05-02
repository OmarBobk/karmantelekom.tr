<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      class="light" style="border:none">
    <head>

        <!-- Google tag (gtag.js) -->
        <script async src="https://www.googletagmanager.com/gtag/js?id=G-ZHW5S051EH"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());

            gtag('config', 'G-ZHW5S051EH');
        </script>

        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title ?? config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <!-- Alpine Cart Store -->
        <script>
            document.addEventListener('alpine:init', () => {
            });
        </script>
    </head>
    <body
        class="min-h-screen bg-gray-50"
        data-user="{{auth()->check() ? auth()->id() : ''}}"
        data-role="{{auth()->user()?->roles()->first()->name ?? ''}}"
    >
        <header>
            <livewire:frontend.partials.header-component />
        </header>
        <main>
            {{ $slot }}
        </main>
        <footer>
            <livewire:frontend.partials.footer-component />
        </footer>

        <!-- Notification Component -->
        <x-notification />

        @stack('scripts')
    </body>
</html>


