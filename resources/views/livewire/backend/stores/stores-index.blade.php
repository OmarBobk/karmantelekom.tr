<div dir="{{ $textDirection }}" lang="{{ $locale }}" class="mx-auto max-w-6xl space-y-6 text-start">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('stores.title') }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ __('stores.subtitle') }}</p>
        </div>
        <button
            type="button"
            wire:click="openCreateForm"
            class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-hidden focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
        >
            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z" />
            </svg>
            {{ __('stores.actions.create') }}
        </button>
    </div>

    @if ($stores->isEmpty())
        <div class="rounded-xl border-2 border-dashed border-gray-300 bg-white px-6 py-12 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72l1.189-1.19A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72M6.75 18h3.75a.75.75 0 0 0 .75-.75V13.5a.75.75 0 0 0-.75-.75H6.75a.75.75 0 0 0-.75.75v3.75c0 .414.336.75.75.75Z" />
            </svg>
            <h2 class="mt-3 text-base font-semibold text-gray-900">{{ __('stores.empty.stores_title') }}</h2>
            <p class="mt-1 text-sm text-gray-500">{{ __('stores.empty.stores_body') }}</p>
        </div>
    @else
        {{-- Tablet and desktop --}}
        <div class="hidden overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 md:block">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-start text-xs font-semibold text-gray-600">{{ __('stores.fields.code') }}</th>
                        <th scope="col" class="px-4 py-3 text-start text-xs font-semibold text-gray-600">{{ __('stores.fields.name') }}</th>
                        <th scope="col" class="px-4 py-3 text-start text-xs font-semibold text-gray-600">{{ __('stores.fields.phone') }}</th>
                        <th scope="col" class="px-4 py-3 text-start text-xs font-semibold text-gray-600">{{ __('stores.fields.address') }}</th>
                        <th scope="col" class="px-4 py-3 text-start text-xs font-semibold text-gray-600">{{ __('stores.fields.employees_count') }}</th>
                        <th scope="col" class="px-4 py-3"><span class="sr-only">{{ __('stores.actions.view') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($stores as $store)
                        <tr wire:key="store-row-{{ $store->id }}" class="hover:bg-gray-50">
                            <td class="whitespace-nowrap px-4 py-3">
                                <span dir="ltr" class="rounded-md bg-gray-100 px-2 py-1 font-mono text-sm text-gray-800">{{ $store->code }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $store->name }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">
                                @if ($store->phone)
                                    <span dir="ltr">{{ $store->phone }}</span>
                                @else
                                    <span class="text-gray-400">{{ __('stores.form.not_provided') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                @if ($store->address)
                                    {{ $store->address }}
                                @else
                                    <span class="whitespace-nowrap text-gray-400">{{ __('stores.form.not_provided') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <span class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-0.5 font-medium text-blue-700">{{ $store->employees_count }}</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-end text-sm">
                                <div class="flex items-center justify-end gap-3">
                                    <button type="button" wire:click="openEditForm({{ $store->id }})" class="font-medium text-gray-600 hover:text-gray-900">{{ __('stores.actions.edit') }}</button>
                                    <a href="{{ route('subdomain.stores.show', $store) }}" class="font-medium text-blue-600 hover:text-blue-800">{{ __('stores.actions.view') }}</a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Mobile --}}
        <ul class="space-y-3 md:hidden">
            @foreach ($stores as $store)
                <li wire:key="store-card-{{ $store->id }}" class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-semibold text-gray-900">{{ $store->name }}</p>
                            <span dir="ltr" class="mt-1 inline-block rounded-md bg-gray-100 px-2 py-0.5 font-mono text-xs text-gray-700">{{ $store->code }}</span>
                        </div>
                        <span class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700">
                            {{ __('stores.fields.employees_count') }}: {{ $store->employees_count }}
                        </span>
                    </div>
                    <dl class="mt-3 space-y-1 text-sm text-gray-600">
                        <div class="flex gap-2">
                            <dt class="text-gray-500">{{ __('stores.fields.phone') }}:</dt>
                            <dd dir="ltr">{{ $store->phone ?: __('stores.form.not_provided') }}</dd>
                        </div>
                        <div class="flex gap-2">
                            <dt class="text-gray-500">{{ __('stores.fields.address') }}:</dt>
                            <dd>{{ $store->address ?: __('stores.form.not_provided') }}</dd>
                        </div>
                    </dl>
                    <div class="mt-4 flex gap-3">
                        <button type="button" wire:click="openEditForm({{ $store->id }})" class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('stores.actions.edit') }}</button>
                        <a href="{{ route('subdomain.stores.show', $store) }}" class="flex-1 rounded-lg bg-blue-600 px-3 py-2 text-center text-sm font-medium text-white hover:bg-blue-700">{{ __('stores.actions.view') }}</a>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($formOpen)
        <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="store-form-title">
            <div class="fixed inset-0 bg-gray-900/50" wire:click="closeForm"></div>
            <div class="relative flex min-h-full items-center justify-center p-4">
                <form wire:submit="save" dir="{{ $textDirection }}" class="relative w-full max-w-lg space-y-5 rounded-xl bg-white p-6 text-start shadow-xl">
                    <h2 id="store-form-title" class="text-lg font-semibold text-gray-900">
                        {{ $editingStoreId ? __('stores.form.edit_title') : __('stores.form.create_title') }}
                    </h2>

                    <div>
                        <label for="store-code" class="block text-sm font-medium text-gray-700">{{ __('stores.fields.code') }}</label>
                        @if ($editingStoreId)
                            <input id="store-code" type="text" value="{{ $code }}" dir="ltr" disabled aria-describedby="store-code-help" class="mt-1 block w-full rounded-lg border-gray-300 bg-gray-100 font-mono text-gray-500 shadow-sm">
                            <p id="store-code-help" class="mt-1 text-xs text-gray-500">{{ __('stores.form.code_locked') }}</p>
                        @else
                            <input id="store-code" type="text" wire:model="code" dir="ltr" autocomplete="off" aria-describedby="store-code-help" @error('code') aria-invalid="true" @enderror class="mt-1 block w-full rounded-lg border-gray-300 font-mono shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <p id="store-code-help" class="mt-1 text-xs text-gray-500">{{ __('stores.form.code_help') }}</p>
                            @error('code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        @endif
                    </div>

                    <div>
                        <label for="store-name" class="block text-sm font-medium text-gray-700">{{ __('stores.fields.name') }}</label>
                        <input id="store-name" type="text" wire:model="name" @error('name') aria-invalid="true" @enderror class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label for="store-phone" class="block text-sm font-medium text-gray-700">{{ __('stores.fields.phone') }}</label>
                            <input id="store-phone" type="tel" wire:model="phone" dir="ltr" @error('phone') aria-invalid="true" @enderror class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @error('phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="store-address" class="block text-sm font-medium text-gray-700">{{ __('stores.fields.address') }}</label>
                            <input id="store-address" type="text" wire:model="address" @error('address') aria-invalid="true" @enderror class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @error('address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <button type="button" wire:click="closeForm" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('stores.actions.cancel') }}</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="save" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60">
                            <span wire:loading.remove wire:target="save">{{ __('stores.actions.save') }}</span>
                            <span wire:loading wire:target="save">{{ __('stores.actions.saving') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
