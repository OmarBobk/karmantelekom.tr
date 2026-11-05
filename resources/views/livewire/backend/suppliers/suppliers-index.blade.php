<div dir="{{ $textDirection }}" lang="{{ $locale }}" class="mx-auto max-w-6xl space-y-6 text-start">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('suppliers.title') }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ __('suppliers.subtitle') }}</p>
        </div>
        <button type="button" wire:click="openCreateForm" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-hidden focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z" />
            </svg>
            {{ __('suppliers.actions.create') }}
        </button>
    </div>

    <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
        <label for="supplier-search" class="block text-sm font-medium text-gray-700">{{ __('suppliers.search_label') }}</label>
        <input id="supplier-search" type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('suppliers.search_placeholder') }}" class="mt-1 block min-h-11 w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
    </div>

    @if ($suppliers->isEmpty())
        <div class="rounded-xl border-2 border-dashed border-gray-300 bg-white px-6 py-12 text-center">
            <h2 class="text-base font-semibold text-gray-900">{{ $isFiltering ? __('suppliers.empty.no_matches_title') : __('suppliers.empty.title') }}</h2>
            <p class="mt-1 text-sm text-gray-500">{{ $isFiltering ? __('suppliers.empty.no_matches_body') : __('suppliers.empty.body') }}</p>
        </div>
    @else
        <div class="hidden overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 md:block" wire:loading.class="opacity-60" wire:target="search,gotoPage,nextPage,previousPage">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-start text-xs font-semibold text-gray-600">{{ __('suppliers.fields.name') }}</th>
                        <th scope="col" class="px-4 py-3 text-start text-xs font-semibold text-gray-600">{{ __('suppliers.fields.contact_name') }}</th>
                        <th scope="col" class="px-4 py-3 text-start text-xs font-semibold text-gray-600">{{ __('suppliers.fields.phone') }}</th>
                        <th scope="col" class="px-4 py-3 text-start text-xs font-semibold text-gray-600">{{ __('suppliers.fields.tax_number') }}</th>
                        <th scope="col" class="px-4 py-3 text-start text-xs font-semibold text-gray-600">{{ __('suppliers.fields.status') }}</th>
                        <th scope="col" class="px-4 py-3"><span class="sr-only">{{ __('suppliers.actions.edit') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($suppliers as $supplier)
                        <tr wire:key="supplier-row-{{ $supplier->id }}" class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <p class="text-sm font-medium text-gray-900"><bdi>{{ $supplier->name }}</bdi></p>
                                @if ($supplier->email)
                                    <p dir="ltr" class="text-start text-xs text-gray-500">{{ $supplier->email }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600"><bdi>{{ $supplier->contact_name ?? '—' }}</bdi></td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600"><span dir="ltr">{{ $supplier->phone ?? '—' }}</span></td>
                            <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-gray-600"><span dir="ltr">{{ $supplier->tax_number ?? '—' }}</span></td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm">
                                @if ($supplier->is_active)
                                    <span class="inline-flex rounded-full bg-green-50 px-2.5 py-0.5 text-xs font-medium text-green-700">{{ __('suppliers.statuses.active') }}</span>
                                @else
                                    <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600">{{ __('suppliers.statuses.inactive') }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-end text-sm">
                                @can('viewAccount', $supplier)
                                    <a href="{{ route('subdomain.suppliers.account', $supplier) }}" wire:navigate class="inline-flex min-h-11 items-center px-2 font-medium text-blue-600 hover:text-blue-800">{{ __('purchases.account.open') }}</a>
                                @endcan
                                <button type="button" wire:click="openEditForm({{ $supplier->id }})" class="min-h-11 px-2 font-medium text-blue-600 hover:text-blue-800">{{ __('suppliers.actions.edit') }}</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <ul class="space-y-3 md:hidden">
            @foreach ($suppliers as $supplier)
                <li wire:key="supplier-card-{{ $supplier->id }}" class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <div class="flex items-start justify-between gap-3">
                        <p class="font-semibold text-gray-900"><bdi>{{ $supplier->name }}</bdi></p>
                        @unless ($supplier->is_active)
                            <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600">{{ __('suppliers.statuses.inactive') }}</span>
                        @endunless
                    </div>
                    <dl class="mt-3 space-y-1 text-sm text-gray-600">
                        <div class="flex gap-2"><dt class="text-gray-500">{{ __('suppliers.fields.contact_name') }}:</dt><dd><bdi>{{ $supplier->contact_name ?? '—' }}</bdi></dd></div>
                        <div class="flex gap-2"><dt class="text-gray-500">{{ __('suppliers.fields.phone') }}:</dt><dd dir="ltr">{{ $supplier->phone ?? '—' }}</dd></div>
                    </dl>
                    <div class="mt-4 flex gap-2">
                        @can('viewAccount', $supplier)
                            <a href="{{ route('subdomain.suppliers.account', $supplier) }}" wire:navigate class="flex min-h-11 flex-1 items-center justify-center rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('purchases.account.open') }}</a>
                        @endcan
                        <button type="button" wire:click="openEditForm({{ $supplier->id }})" class="min-h-11 flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('suppliers.actions.edit') }}</button>
                    </div>
                </li>
            @endforeach
        </ul>

        {{ $suppliers->links() }}
    @endif

    @if ($formOpen)
        <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="supplier-form-title" x-data x-on:keydown.escape.window="$wire.closeForm()">
            <div class="fixed inset-0 bg-gray-900/50" wire:click="closeForm"></div>
            <div class="relative flex min-h-full items-center justify-center p-4">
                <form wire:submit="save" dir="{{ $textDirection }}" class="relative w-full max-w-2xl space-y-5 rounded-xl bg-white p-6 text-start shadow-xl">
                    <h2 id="supplier-form-title" class="text-lg font-semibold text-gray-900">
                        {{ $editingSupplierId ? __('suppliers.form.edit_title') : __('suppliers.form.create_title') }}
                    </h2>

                    <div class="grid gap-5 sm:grid-cols-2">
                        @foreach ([
                            ['name', 'name', 'text', 'auto', 'sm:col-span-2', 'organization'],
                            ['contactName', 'contact_name', 'text', 'auto', '', 'name'],
                            ['phone', 'phone', 'tel', 'ltr', '', 'tel'],
                            ['email', 'email', 'email', 'ltr', '', 'email'],
                            ['taxNumber', 'tax_number', 'text', 'ltr', '', 'off'],
                            ['address', 'address', 'text', 'auto', 'sm:col-span-2', 'street-address'],
                        ] as [$property, $label, $type, $direction, $span, $autocomplete])
                            <div class="{{ $span }}">
                                <label for="supplier-{{ $property }}" class="block text-sm font-medium text-gray-700">{{ __("suppliers.fields.{$label}") }}</label>
                                <input
                                    id="supplier-{{ $property }}"
                                    type="{{ $type }}"
                                    wire:model="{{ $property }}"
                                    dir="{{ $direction }}"
                                    autocomplete="{{ $autocomplete }}"
                                    @if ($property === 'taxNumber') spellcheck="false" @endif
                                    @if ($property === 'name') x-init="$el.focus()" @endif
                                    @error($property) aria-invalid="true" aria-describedby="supplier-{{ $property }}-error" @enderror
                                    class="mt-1 block min-h-11 w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                >
                                @error($property) <p id="supplier-{{ $property }}-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        @endforeach

                        <div class="sm:col-span-2">
                            <label for="supplier-notes" class="block text-sm font-medium text-gray-700">{{ __('suppliers.fields.notes') }}</label>
                            <textarea id="supplier-notes" wire:model="notes" rows="3" @error('notes') aria-invalid="true" @enderror class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                            @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <label class="flex min-h-11 items-center gap-3 sm:col-span-2">
                            <input type="checkbox" wire:model="isActive" class="h-5 w-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="text-sm text-gray-700">{{ __('suppliers.form.active') }}</span>
                        </label>
                    </div>

                    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <button type="button" wire:click="closeForm" class="min-h-11 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('suppliers.actions.cancel') }}</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="save" class="min-h-11 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60">
                            <span wire:loading.remove wire:target="save">{{ __('suppliers.actions.save') }}</span>
                            <span wire:loading wire:target="save">{{ __('suppliers.actions.saving') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
