<div dir="{{ $textDirection }}" lang="{{ $locale }}" class="mx-auto max-w-5xl space-y-6 text-start">
    <div>
        <a href="{{ route('subdomain.stores') }}" class="inline-flex items-center gap-1 text-sm font-medium text-blue-600 hover:text-blue-800">
            <svg class="h-4 w-4 rtl:rotate-180" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 0 1-.02 1.06L8.832 10l3.938 3.71a.75.75 0 1 1-1.04 1.08l-4.5-4.25a.75.75 0 0 1 0-1.08l4.5-4.25a.75.75 0 0 1 1.06.02Z" clip-rule="evenodd" />
            </svg>
            {{ __('stores.actions.back') }}
        </a>
        <div class="mt-3 flex flex-wrap items-center gap-3">
            <h1 class="text-2xl font-bold text-gray-900">{{ $store->name }}</h1>
            <span dir="ltr" class="rounded-md bg-gray-100 px-2 py-1 font-mono text-sm text-gray-700">{{ $store->code }}</span>
        </div>
        <dl class="mt-2 flex flex-wrap gap-x-6 gap-y-1 text-sm text-gray-600">
            <div class="flex gap-1">
                <dt class="text-gray-500">{{ __('stores.fields.phone') }}:</dt>
                <dd dir="ltr">{{ $store->phone ?: __('stores.form.not_provided') }}</dd>
            </div>
            <div class="flex gap-1">
                <dt class="text-gray-500">{{ __('stores.fields.address') }}:</dt>
                <dd>{{ $store->address ?: __('stores.form.not_provided') }}</dd>
            </div>
        </dl>
    </div>

    <section class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200" aria-labelledby="assign-employee-title">
        <h2 id="assign-employee-title" class="text-base font-semibold text-gray-900">{{ __('stores.employees.assign_title') }}</h2>

        @if ($availableEmployees->isEmpty())
            <p class="mt-2 text-sm text-gray-500">{{ __('stores.empty.no_available_employees') }}</p>
        @else
            <form wire:submit="assign" class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-start">
                <div class="flex-1">
                    <label for="employee-select" class="sr-only">{{ __('stores.fields.employee') }}</label>
                    <select id="employee-select" wire:model="selectedEmployeeId" @error('selectedEmployeeId') aria-invalid="true" @enderror class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">{{ __('stores.employees.select_placeholder') }}</option>
                        @foreach ($availableEmployees as $candidate)
                            <option value="{{ $candidate->id }}">{{ $candidate->name }} — {{ $candidate->email }}</option>
                        @endforeach
                    </select>
                    @error('selectedEmployeeId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <button type="submit" wire:loading.attr="disabled" wire:target="assign" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60">
                    <span wire:loading.remove wire:target="assign">{{ __('stores.actions.assign') }}</span>
                    <span wire:loading wire:target="assign">{{ __('stores.actions.assigning') }}</span>
                </button>
            </form>
        @endif
    </section>

    <section class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200" aria-labelledby="assigned-employees-title">
        <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4">
            <h2 id="assigned-employees-title" class="text-base font-semibold text-gray-900">{{ __('stores.employees.title') }}</h2>
            <span class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-0.5 text-sm font-medium text-blue-700">{{ $employees->count() }}</span>
        </div>
        @if ($canManagePos && $employees->isNotEmpty())
            <p id="pos-access-scope-note" class="border-b border-gray-100 bg-amber-50 px-5 py-2 text-sm text-amber-900">{{ __('pos.access.scope_note') }}</p>
        @endif

        @if ($employees->isEmpty())
            <div class="px-6 py-10 text-center">
                <svg class="mx-auto h-10 w-10 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                </svg>
                <h3 class="mt-3 text-sm font-semibold text-gray-900">{{ __('stores.empty.employees_title') }}</h3>
                <p class="mt-1 text-sm text-gray-500">{{ __('stores.empty.employees_body') }}</p>
            </div>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach ($employees as $employee)
                    <li wire:key="employee-{{ $employee->id }}" class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <p class="font-medium text-gray-900">{{ $employee->name }}</p>
                            <p class="truncate text-sm text-gray-500"><span dir="ltr">{{ $employee->email }}</span></p>
                            <p class="mt-1 flex flex-wrap items-center gap-1 text-xs text-gray-500">
                                <span>{{ __('stores.employees.other_stores') }}:</span>
                                @forelse ($employee->stores as $otherStore)
                                    <span dir="ltr" class="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-gray-700">{{ $otherStore->code }}</span>
                                @empty
                                    <span>{{ __('stores.employees.none') }}</span>
                                @endforelse
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 self-start sm:self-center">
                            @if ($canManagePos)
                                @php($hasPos = $employee->permissions->contains('name', $posPermission))
                                <button
                                    type="button"
                                    role="switch"
                                    aria-checked="{{ $hasPos ? 'true' : 'false' }}"
                                    aria-describedby="pos-access-scope-note"
                                    wire:click="setPosAccess({{ $employee->id }}, {{ $hasPos ? 'false' : 'true' }})"
                                    wire:loading.attr="disabled"
                                    wire:target="setPosAccess"
                                    @if ($hasPos) wire:confirm="{{ __('pos.access.revoke_confirm', ['name' => $employee->name]) }}" @endif
                                    @class([
                                        'inline-flex min-h-10 items-center gap-2 rounded-lg px-3 py-1.5 text-sm font-medium ring-1 disabled:opacity-60',
                                        'bg-emerald-50 text-emerald-800 ring-emerald-300 hover:bg-emerald-100' => $hasPos,
                                        'bg-white text-gray-700 ring-gray-300 hover:bg-gray-50' => ! $hasPos,
                                    ])
                                >
                                    <span @class(['h-2.5 w-2.5 rounded-full', 'bg-emerald-600' => $hasPos, 'bg-gray-300' => ! $hasPos]) aria-hidden="true"></span>
                                    {{ __('pos.access.toggle_label') }}: {{ $hasPos ? __('pos.access.on') : __('pos.access.off') }}
                                </button>
                                @php($discountGrant = $employee->permissions->contains('name', $discountPermission) ? $employee->posDiscountGrant : null)
                                <button
                                    type="button"
                                    wire:click="openDiscountGrant({{ $employee->id }})"
                                    aria-haspopup="dialog"
                                    @class([
                                        'inline-flex min-h-10 items-center gap-2 rounded-lg px-3 py-1.5 text-sm font-medium ring-1',
                                        'bg-blue-50 text-blue-800 ring-blue-300 hover:bg-blue-100' => $discountGrant,
                                        'bg-white text-gray-700 ring-gray-300 hover:bg-gray-50' => ! $discountGrant,
                                    ])
                                >
                                    {{ __('pos.access.discount_label') }}:
                                    @if ($discountGrant)
                                        {{ __('pos.access.discount_up_to', ['percent' => $discountGrant->percentLabel()]) }}
                                    @else
                                        {{ __('pos.access.off') }}
                                    @endif
                                </button>
                            @endif
                            <button type="button" wire:click="confirmRemoval({{ $employee->id }})" class="rounded-lg border border-red-200 px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50">
                                {{ __('stores.actions.remove') }}
                            </button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    @if ($canManagePos)
        <section class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200" aria-labelledby="card-terminals-title">
            <div class="border-b border-gray-200 px-5 py-4">
                <h2 id="card-terminals-title" class="text-base font-semibold text-gray-900">{{ __('pos.terminals.title') }}</h2>
                <p class="mt-1 text-sm text-gray-600">{{ __('pos.terminals.help') }}</p>
            </div>

            @if ($terminals->isNotEmpty())
                <ul class="divide-y divide-gray-100">
                    @foreach ($terminals as $terminal)
                        <li wire:key="terminal-{{ $terminal->id }}" class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                            <div>
                                <p class="font-medium text-gray-900">{{ $terminal->label }}</p>
                                <p class="text-sm text-gray-600">
                                    {{ __('pos.terminals.fields.merchant_number') }}: <bdi dir="ltr" class="font-mono">{{ $terminal->merchant_number }}</bdi>
                                    · {{ __('pos.terminals.fields.terminal_number') }}: <bdi dir="ltr" class="font-mono">{{ $terminal->terminal_number }}</bdi>
                                </p>
                            </div>
                            <button type="button" wire:click="setTerminalActive({{ $terminal->id }}, {{ $terminal->is_active ? 'false' : 'true' }})" class="rounded-lg px-3 py-1.5 text-sm font-medium ring-1 ring-gray-300 hover:bg-gray-50">
                                {{ $terminal->is_active ? __('pos.terminals.deactivate') : __('pos.terminals.activate') }}
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif

            <form wire:submit="addTerminal" class="grid gap-3 border-t border-gray-100 px-5 py-4 sm:grid-cols-4 sm:items-end">
                <div>
                    <label for="terminal-label" class="block text-sm font-medium text-gray-700">{{ __('pos.terminals.fields.label') }}</label>
                    <input id="terminal-label" type="text" maxlength="100" wire:model="terminalLabel" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    @error('label') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="terminal-merchant" class="block text-sm font-medium text-gray-700">{{ __('pos.terminals.fields.merchant_number') }}</label>
                    <input id="terminal-merchant" type="text" dir="ltr" maxlength="32" wire:model="terminalMerchantNumber" class="mt-1 block w-full rounded-lg border-gray-300 font-mono shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    @error('merchant_number') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="terminal-number" class="block text-sm font-medium text-gray-700">{{ __('pos.terminals.fields.terminal_number') }}</label>
                    <input id="terminal-number" type="text" dir="ltr" maxlength="32" wire:model="terminalNumber" class="mt-1 block w-full rounded-lg border-gray-300 font-mono shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    @error('terminal_number') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <button type="submit" wire:loading.attr="disabled" wire:target="addTerminal" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60">{{ __('pos.terminals.add') }}</button>
            </form>
        </section>
    @endif

    @if ($canManagePos)
        <x-operations.dialog :id="\App\Livewire\Backend\Stores\StoreDetail::DISCOUNT_DIALOG" :title="__('pos.access.discount_title', ['name' => $discountEmployee?->name ?? ''])" :direction="$textDirection">
            <form wire:submit="saveDiscountGrant" class="space-y-4">
                <p class="text-sm text-gray-600">{{ __('pos.access.discount_help') }}</p>
                <div>
                    <label for="discount-percent" class="block text-sm font-medium text-gray-700">{{ __('pos.access.discount_percent') }}</label>
                    <div class="mt-1 flex items-center gap-2">
                        <input id="discount-percent" type="text" inputmode="decimal" dir="ltr" maxlength="6" wire:model="discountPercent" aria-describedby="discount-percent-help" @error('discountPercent') aria-invalid="true" @enderror class="block w-32 rounded-lg border-gray-300 font-mono shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <span class="text-sm text-gray-600" aria-hidden="true">%</span>
                    </div>
                    <p id="discount-percent-help" class="mt-1 text-xs text-gray-500">{{ __('pos.access.discount_percent_help') }}</p>
                    @error('discountPercent') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button type="button" x-on:click="$el.closest('dialog').close()" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('stores.actions.cancel') }}</button>
                    <button type="submit" wire:loading.attr="disabled" wire:target="saveDiscountGrant" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60">{{ __('pos.access.discount_save') }}</button>
                </div>
            </form>
        </x-operations.dialog>
    @endif

    @if ($employeeToRemove)
        <div class="fixed inset-0 z-50 overflow-y-auto" role="alertdialog" aria-modal="true" aria-labelledby="remove-employee-title" aria-describedby="remove-employee-body">
            <div class="fixed inset-0 bg-gray-900/50" wire:click="cancelRemoval"></div>
            <div class="relative flex min-h-full items-center justify-center p-4">
                <div dir="{{ $textDirection }}" class="relative w-full max-w-md rounded-xl bg-white p-6 text-start shadow-xl">
                    <h2 id="remove-employee-title" class="text-lg font-semibold text-gray-900">{{ __('stores.remove.title') }}</h2>
                    <div id="remove-employee-body" class="mt-3 space-y-2 text-sm text-gray-600">
                        <p>{{ __('stores.remove.body', ['name' => $employeeToRemove->name, 'store' => $store->name]) }}</p>
                        <p class="rounded-lg bg-blue-50 p-3 text-blue-800">{{ __('stores.remove.account_note') }}</p>
                    </div>
                    <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <button type="button" wire:click="cancelRemoval" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('stores.actions.cancel') }}</button>
                        <button type="button" wire:click="remove" wire:loading.attr="disabled" wire:target="remove" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-60">{{ __('stores.actions.confirm_remove') }}</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
