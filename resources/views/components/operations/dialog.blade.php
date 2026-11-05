{{--
    Native modal dialog opened in the browser only: $dispatch('open-dialog', { id: '...' }).
    The server closes it after a confirmed success with $this->dispatch('close-dialog', id: '...').
    wire:ignore.self keeps Livewire re-renders from dropping the open state; focus returns to the opener.
--}}
@props(['id', 'title', 'direction' => 'ltr'])

<dialog
    wire:ignore.self
    x-data="{ trigger: null }"
    x-on:open-dialog.window="if ($event.detail.id === @js($id)) { trigger = document.activeElement; $el.showModal(); }"
    x-on:close-dialog.window="if ($event.detail.id === @js($id)) { $el.close(); }"
    x-on:close="trigger?.focus?.()"
    aria-labelledby="{{ $id }}-title"
    dir="{{ $direction }}"
    {{ $attributes->merge(['class' => 'm-auto w-[calc(100%-2rem)] max-w-lg rounded-xl bg-white p-0 text-start shadow-xl backdrop:bg-gray-900/50']) }}
>
    <div class="flex items-start justify-between gap-3 border-b border-gray-100 px-5 py-4">
        <h2 id="{{ $id }}-title" class="text-base font-semibold text-gray-900">{{ $title }}</h2>
        <button type="button" x-on:click="$el.closest('dialog').close()" class="-m-2 inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-700" aria-label="{{ __('purchases.form.close') }}">
            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg>
        </button>
    </div>
    <div class="px-5 py-4">
        {{ $slot }}
    </div>
</dialog>
