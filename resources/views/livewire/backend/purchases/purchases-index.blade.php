<div dir="{{ $textDirection }}" lang="{{ $locale }}" class="mx-auto max-w-6xl space-y-6 text-start">
    @php($field = 'mt-1 block min-h-11 w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500')
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('purchases.title') }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ __('purchases.subtitle') }}</p>
        </div>
        @if ($canCreate)
            <button type="button" x-on:click="$dispatch('open-dialog', { id: 'create-purchase' })" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z"/></svg>
                {{ __('purchases.index.new') }}
            </button>
        @endif
    </div>

    <div class="grid gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 sm:grid-cols-3">
        <div class="sm:col-span-1">
            <label for="purchase-search" class="block text-sm font-medium text-gray-700">{{ __('purchases.filters.search') }}</label>
            <input id="purchase-search" type="search" wire:model.live.debounce.400ms="search" autocomplete="off" spellcheck="false" class="{{ $field }}">
        </div>
        <div>
            <label for="purchase-view" class="block text-sm font-medium text-gray-700">{{ __('purchases.filters.view') }}</label>
            <select id="purchase-view" wire:model.live="view" class="{{ $field }}">
                @foreach ($views as $option)
                    <option value="{{ $option }}">{{ __('purchases.filters.'.$option) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="purchase-store" class="block text-sm font-medium text-gray-700">{{ __('purchases.filters.store') }}</label>
            <select id="purchase-store" wire:model.live="store" class="{{ $field }}">
                <option value="">{{ __('purchases.filters.all_stores') }}</option>
                @foreach ($stores as $storeOption)
                    <option value="{{ $storeOption->id }}">{{ $storeOption->code }} · {{ $storeOption->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="relative">
        <p wire:loading.flex wire:target="search,view,store,gotoPage,nextPage,previousPage" role="status" class="absolute -top-5 end-0 hidden items-center gap-2 text-xs text-gray-500">
            <svg class="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"></path></svg>
            {{ __('purchases.index.loading') }}
        </p>

        @if ($purchases->isEmpty())
            <div class="rounded-xl border-2 border-dashed border-gray-300 bg-white px-6 py-12 text-center">
                <h2 class="text-base font-semibold text-gray-900">{{ $isFiltering ? __('purchases.index.no_matches_title') : __('purchases.index.empty_title') }}</h2>
                <p class="mt-1 text-sm text-gray-500">{{ $isFiltering ? __('purchases.index.no_matches_body') : __('purchases.index.empty_body') }}</p>
            </div>
        @else
            <div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-200" wire:loading.class="opacity-60" wire:target="search,view,store,gotoPage,nextPage,previousPage">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-start text-xs font-semibold text-gray-600">{{ __('purchases.fields.invoice_number') }}</th>
                            <th scope="col" class="px-4 py-3 text-start text-xs font-semibold text-gray-600">{{ __('purchases.fields.supplier') }}</th>
                            <th scope="col" class="hidden px-4 py-3 text-start text-xs font-semibold text-gray-600 lg:table-cell">{{ __('purchases.fields.invoice_date') }}</th>
                            <th scope="col" class="px-4 py-3 text-start text-xs font-semibold text-gray-600">{{ __('purchases.fields.status') }}</th>
                            <th scope="col" class="px-4 py-3 text-end text-xs font-semibold text-gray-600">{{ __('purchases.fields.total') }}</th>
                            <th scope="col" class="px-4 py-3 text-end text-xs font-semibold text-gray-600">{{ __('purchases.fields.outstanding') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($purchases as $purchase)
                            <tr wire:key="purchase-row-{{ $purchase->id }}" class="relative hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <a href="{{ route('subdomain.purchases.show', $purchase) }}" wire:navigate class="font-medium text-gray-900 after:absolute after:inset-0 hover:text-blue-700">
                                        @if ($purchase->supplier_invoice_number)
                                            <bdi dir="ltr" class="font-mono">{{ $purchase->supplier_invoice_number }}</bdi>
                                        @else
                                            {{ __('purchases.index.draft_label', ['id' => $purchase->id]) }}
                                        @endif
                                    </a>
                                    <p class="text-xs text-gray-500"><span dir="ltr" class="font-mono">{{ $purchase->store->code }}</span></p>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700"><bdi>{{ $purchase->supplier->name }}</bdi></td>
                                <td class="hidden whitespace-nowrap px-4 py-3 text-sm text-gray-600 lg:table-cell"><span dir="ltr">{{ $purchase->invoice_date?->format('Y-m-d') ?? '—' }}</span></td>
                                <td class="px-4 py-3">@include('livewire.backend.purchases.partials.badges', ['purchase' => $purchase])</td>
                                <td class="px-4 py-3 text-end text-sm text-gray-900">@include('livewire.backend.purchases.partials.money', ['minor' => $purchase->totalMinor(), 'currency' => $purchase->currency])</td>
                                <td class="px-4 py-3 text-end text-sm">
                                    @if ($purchase->isPosted())
                                        @include('livewire.backend.purchases.partials.money', ['minor' => $purchase->outstandingMinor(), 'currency' => $purchase->currency, 'class' => $purchase->outstandingMinor() > 0 ? 'font-semibold text-gray-900' : 'text-gray-400'])
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $purchases->links() }}</div>
        @endif
    </div>

    @if ($canCreate)
        <x-operations.dialog id="create-purchase" :title="__('purchases.create.title')" :direction="$textDirection">
            <form wire:submit="createDraft" class="space-y-4">
                <p class="text-sm text-gray-600">{{ __('purchases.create.help') }}</p>
                @if ($suppliers->isEmpty())
                    <p class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900" role="status">{{ __('purchases.create.no_suppliers') }}</p>
                @endif
                <div>
                    <label for="new-store" class="block text-sm font-medium text-gray-700">{{ __('purchases.fields.store') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                    <select id="new-store" wire:model="newStoreId" autofocus required @error('newStoreId') aria-invalid="true" aria-describedby="new-store-error" @enderror class="{{ $field }}">
                        <option value="">{{ __('purchases.form.choose') }}</option>
                        @foreach ($stores as $storeOption)
                            <option value="{{ $storeOption->id }}">{{ $storeOption->code }} · {{ $storeOption->name }}</option>
                        @endforeach
                    </select>
                    @error('newStoreId') <p id="new-store-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="new-supplier" class="block text-sm font-medium text-gray-700">{{ __('purchases.fields.supplier') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                    <select id="new-supplier" wire:model="newSupplierId" required @error('newSupplierId') aria-invalid="true" aria-describedby="new-supplier-error" @enderror class="{{ $field }}">
                        <option value="">{{ __('purchases.form.choose') }}</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                    @error('newSupplierId') <p id="new-supplier-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="new-currency" class="block text-sm font-medium text-gray-700">{{ __('purchases.fields.currency') }}</label>
                        <select id="new-currency" wire:model="newCurrency" required class="{{ $field }}">
                            @foreach ($currencies as $currency)
                                <option value="{{ $currency->value }}">{{ $currency->label() }}</option>
                            @endforeach
                        </select>
                        @error('newCurrency') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="new-tax" class="block text-sm font-medium text-gray-700">{{ __('purchases.fields.tax_treatment') }}</label>
                        <select id="new-tax" wire:model="newTaxTreatment" required class="{{ $field }}">
                            @foreach ($taxTreatments as $treatment)
                                <option value="{{ $treatment->value }}">{{ $treatment->label() }}</option>
                            @endforeach
                        </select>
                        @error('newTaxTreatment') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="flex flex-col-reverse gap-2 border-t border-gray-100 pt-4 sm:flex-row sm:justify-end">
                    <button type="button" x-on:click="$el.closest('dialog').close()" class="min-h-11 rounded-lg px-4 py-2 text-sm font-medium text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50">{{ __('purchases.form.cancel') }}</button>
                    <button type="submit" wire:loading.attr="disabled" wire:target="createDraft" class="min-h-11 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60">
                        <span wire:loading.remove wire:target="createDraft">{{ __('purchases.create.submit') }}</span>
                        <span wire:loading wire:target="createDraft">{{ __('purchases.draft.saving') }}</span>
                    </button>
                </div>
            </form>
        </x-operations.dialog>
    @endif
</div>
