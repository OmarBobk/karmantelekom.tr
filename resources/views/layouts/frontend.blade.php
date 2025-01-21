<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light" style="border:none">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{{ $title ?? config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-gray-50">
        <header>
            <livewire:frontend.partials.header-component />
        </header>
        <main>
            {{ $slot }}
        </main>
        <footer>
            <livewire:frontend.partials.footer-component />
        </footer>
        @stack('scripts')
    </body>
</html>


