<section
    dir="{{ $textDirection }}"
    lang="{{ $locale }}"
    aria-labelledby="device-lookup-title-{{ $this->getId() }}"
    class="rounded-xl bg-white p-5 text-start shadow-sm ring-1 ring-gray-200"
    x-data="{
        sequence: 0,
        pending: false,
        failed: false,
        async submit() {
            const code = this.$refs.code.value;
            if (code.trim() === '') {
                this.$refs.code.focus();
                return;
            }
            const sequence = ++this.sequence;
            this.pending = true;
            this.failed = false;
            try {
                const result = await $wire.lookup(code, sequence);
                if (sequence !== this.sequence) {
                    return;
                }
                if (result.url) {
                    window.location.assign(result.url);
                    return;
                }
                this.pending = false;
                this.$nextTick(() => this.$root.querySelector('[data-lookup-result]')?.focus());
            } catch (error) {
                if (sequence === this.sequence) {
                    this.pending = false;
                    this.failed = true;
                }
            }
        },
    }"
>
    <h2 id="device-lookup-title-{{ $this->getId() }}" class="text-base font-semibold text-gray-900">{{ __('devices.lookup.title') }}</h2>
    <p id="device-lookup-help-{{ $this->getId() }}" class="mt-1 text-sm text-gray-600">{{ $scoped ? __('devices.lookup.help_store') : __('devices.lookup.help_all') }}</p>

    <form role="search" x-on:submit.prevent="submit()" class="mt-3 flex flex-wrap items-stretch gap-2">
        <label for="device-lookup-code-{{ $this->getId() }}" class="sr-only">{{ __('devices.lookup.label') }}</label>
        <div wire:ignore class="min-w-0 flex-1 basis-64">
            <input
                x-ref="code"
                id="device-lookup-code-{{ $this->getId() }}"
                type="text"
                inputmode="text"
                enterkeyhint="search"
                dir="ltr"
                maxlength="80"
                autocomplete="off"
                autocapitalize="off"
                spellcheck="false"
                data-scan
                aria-describedby="device-lookup-help-{{ $this->getId() }}"
                placeholder="{{ __('devices.lookup.placeholder') }}"
                class="block min-h-12 w-full rounded-lg border-gray-300 font-mono text-lg tracking-wide shadow-sm focus:border-blue-500 focus:ring-blue-500"
            >
        </div>
        <button type="submit" x-bind:aria-busy="pending" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-lg bg-blue-600 px-5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">
            <svg x-show="pending" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".3" stroke-width="3"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
            <span>{{ __('devices.lookup.submit') }}</span>
        </button>
    </form>

    <div aria-live="polite" class="mt-3 space-y-3">
        <p x-show="pending" x-cloak class="text-sm text-gray-600">{{ __('devices.lookup.searching') }}</p>
        <p x-show="failed" x-cloak class="text-sm text-red-700">{{ __('devices.lookup.failed') }}</p>

        @if ($result !== null)
            <div x-show="! pending">
                <p class="text-sm text-gray-700">
                    {!! __('devices.lookup.results_for', ['code' => '<bdi dir="ltr" class="font-mono font-semibold text-gray-900">'.e($code).'</bdi>']) !!}
                </p>

                @if ($result['current'] === [] && $result['historical'] === [])
                    <p data-lookup-result tabindex="-1" class="mt-2 rounded-lg bg-gray-50 px-3 py-3 text-sm text-gray-700 ring-1 ring-gray-200 focus:outline-2 focus:outline-blue-600">{{ __('devices.lookup.none') }}</p>
                @endif

                @if (count($result['current']) > 1)
                    <p class="mt-2 text-sm font-medium text-amber-800">{{ __('devices.lookup.choose', ['count' => count($result['current'])]) }}</p>
                @endif

                @if ($result['current'] !== [])
                    <ul class="mt-2 divide-y divide-gray-100 overflow-hidden rounded-lg ring-1 ring-gray-200">
                        @foreach ($result['current'] as $match)
                            @include('livewire.backend.inventory.partials.lookup-match', ['match' => $match, 'historical' => false, 'first' => $loop->first])
                        @endforeach
                    </ul>
                @endif

                @if ($result['historical'] !== [])
                    <h3 class="mt-4 text-sm font-semibold text-gray-900">{{ __('devices.lookup.historical_title') }}</h3>
                    <p class="text-xs text-gray-600">{{ __('devices.lookup.historical_help') }}</p>
                    <ul class="mt-2 divide-y divide-gray-100 overflow-hidden rounded-lg ring-1 ring-amber-200">
                        @foreach ($result['historical'] as $match)
                            @include('livewire.backend.inventory.partials.lookup-match', ['match' => $match, 'historical' => true, 'first' => $loop->first && $result['current'] === []])
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif
    </div>
</section>
