{{-- Expects: $kind ('brand'|'category'), $records, $newField, $editingKind, $editingId --}}
<section aria-labelledby="{{ $kind }}-panel-title" class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
    <div class="border-b border-gray-100 p-4">
        <h2 id="{{ $kind }}-panel-title" class="text-base font-semibold text-gray-900">{{ __("catalog.settings.{$kind}_title") }}</h2>
        <form wire:submit="create('{{ $kind }}')" class="mt-3 flex items-start gap-2">
            <div class="flex-1">
                <label for="new-{{ $kind }}" class="sr-only">{{ __("catalog.settings.new_{$kind}") }}</label>
                <input
                    id="new-{{ $kind }}"
                    type="text"
                    wire:model="{{ $newField }}"
                    placeholder="{{ __("catalog.settings.new_{$kind}") }}"
                    @error($newField) aria-invalid="true" aria-describedby="new-{{ $kind }}-error" @enderror
                    class="block min-h-11 w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                >
                @error($newField) <p id="new-{{ $kind }}-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <button type="submit" wire:loading.attr="disabled" wire:target="create('{{ $kind }}')" class="min-h-11 shrink-0 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60">
                {{ __('catalog.actions.add') }}
            </button>
        </form>
    </div>

    @if ($records->isEmpty())
        <p class="px-4 py-10 text-center text-sm text-gray-500">{{ __("catalog.empty.{$kind}") }}</p>
    @else
        <ul class="divide-y divide-gray-100">
            @foreach ($records as $record)
                <li wire:key="{{ $kind }}-{{ $record->id }}" class="px-4 py-3">
                    @if ($editingKind === $kind && $editingId === $record->id)
                        <form wire:submit="update" class="flex items-start gap-2">
                            <div class="flex-1">
                                <label for="edit-{{ $kind }}-{{ $record->id }}" class="sr-only">{{ __('catalog.fields.name') }}</label>
                                <input id="edit-{{ $kind }}-{{ $record->id }}" type="text" wire:model="editingName" x-init="$el.focus()" @error('editingName') aria-invalid="true" @enderror class="block min-h-11 w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                @error('editingName') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <button type="submit" class="min-h-11 rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">{{ __('catalog.actions.save') }}</button>
                            <button type="button" wire:click="cancelEdit" class="min-h-11 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('catalog.actions.cancel') }}</button>
                        </form>
                    @else
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                            <div class="min-w-0 flex-1">
                                <p @class(['truncate text-sm font-medium', 'text-gray-900' => $record->is_active, 'text-gray-500 line-through' => ! $record->is_active])><bdi>{{ $record->name }}</bdi></p>
                                <p class="text-xs text-gray-500">{{ trans_choice('catalog.settings.definitions_count', $record->product_definitions_count, ['count' => $record->product_definitions_count]) }}</p>
                            </div>
                            @unless ($record->is_active)
                                <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600">{{ __('catalog.statuses.inactive') }}</span>
                            @endunless
                            <div class="flex items-center gap-1">
                                <button type="button" wire:click="edit('{{ $kind }}', {{ $record->id }})" class="min-h-11 rounded-lg px-3 text-sm font-medium text-blue-600 hover:bg-blue-50">{{ __('catalog.actions.rename') }}</button>
                                <button type="button" wire:click="toggleActive('{{ $kind }}', {{ $record->id }})" class="min-h-11 rounded-lg px-3 text-sm font-medium text-gray-600 hover:bg-gray-100">
                                    {{ $record->is_active ? __('catalog.actions.deactivate') : __('catalog.actions.activate') }}
                                </button>
                            </div>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</section>
