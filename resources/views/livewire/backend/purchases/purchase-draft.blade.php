<div dir="{{ $textDirection }}" lang="{{ $locale }}" class="mx-auto max-w-6xl space-y-6 text-start">
    @php
        $field = 'mt-1 block min-h-11 w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500';
        $invalid = 'border-red-400 focus:border-red-500 focus:ring-red-500';
    @endphp

    @include('livewire.backend.inventory.partials.breadcrumbs', ['items' => [
        [__('purchases.title'), route('subdomain.purchases')],
        [__('purchases.index.draft_label', ['id' => $purchase->id]), null],
    ]])

    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('purchases.draft.title') }} <span class="text-gray-400">#{{ $purchase->id }}</span></h1>
            @if ($purchase->correctionOf)
                <p class="mt-1 text-sm text-rose-800">
                    <a href="{{ route('subdomain.purchases.show', $purchase->correctionOf) }}" wire:navigate class="underline hover:no-underline">{{ __('purchases.draft.correction_of', ['number' => $purchase->correctionOf->supplier_invoice_number]) }}</a>
                </p>
            @endif
        </div>
        @include('livewire.backend.purchases.partials.badges', ['purchase' => $purchase])
    </div>

    @error('revision')
        <div role="alert" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200">{{ $message }}</div>
    @enderror

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <section aria-labelledby="draft-header-title" class="space-y-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                <h2 id="draft-header-title" class="text-base font-semibold text-gray-900">{{ __('purchases.draft.header') }}</h2>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="draft-store" class="block text-sm font-medium text-gray-700">{{ __('purchases.fields.store') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                        <select id="draft-store" wire:model="storeId" required @error('storeId') aria-invalid="true" aria-describedby="draft-store-error" @enderror @class([$field, $invalid => $errors->has('storeId')])>
                            @foreach ($stores as $store)
                                <option value="{{ $store->id }}">{{ $store->code }} · {{ $store->name }}</option>
                            @endforeach
                        </select>
                        @error('storeId') <p id="draft-store-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="draft-supplier" class="block text-sm font-medium text-gray-700">{{ __('purchases.fields.supplier') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                        <select id="draft-supplier" wire:model="supplierId" required @error('supplierId') aria-invalid="true" aria-describedby="draft-supplier-error" @enderror @class([$field, $invalid => $errors->has('supplierId')])>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" @disabled(! $supplier->is_active)>{{ $supplier->name }}</option>
                            @endforeach
                        </select>
                        @error('supplierId') <p id="draft-supplier-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="draft-currency" class="block text-sm font-medium text-gray-700">{{ __('purchases.fields.currency') }}</label>
                        <select id="draft-currency" wire:model="currency" required class="{{ $field }}">
                            @foreach ($currencies as $currencyOption)
                                <option value="{{ $currencyOption->value }}">{{ $currencyOption->label() }}</option>
                            @endforeach
                        </select>
                        @error('currency') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="draft-tax" class="block text-sm font-medium text-gray-700">{{ __('purchases.fields.tax_treatment') }}</label>
                        <select id="draft-tax" wire:model="taxTreatment" required class="{{ $field }}">
                            @foreach ($taxTreatments as $treatment)
                                <option value="{{ $treatment->value }}">{{ $treatment->label() }}</option>
                            @endforeach
                        </select>
                        @error('taxTreatment') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="draft-invoice-number" class="block text-sm font-medium text-gray-700">{{ __('purchases.fields.invoice_number') }}</label>
                        <input id="draft-invoice-number" type="text" wire:model="invoiceNumber" dir="ltr" maxlength="64" autocomplete="off" spellcheck="false" aria-describedby="draft-invoice-number-help @error('invoiceNumber') draft-invoice-number-error @enderror" @error('invoiceNumber') aria-invalid="true" @enderror @class([$field, 'font-mono text-start', $invalid => $errors->has('invoiceNumber')])>
                        <p id="draft-invoice-number-help" class="mt-1 text-xs text-gray-500">{{ __('purchases.help.invoice_number') }}</p>
                        @error('invoiceNumber') <p id="draft-invoice-number-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="draft-invoice-date" class="block text-sm font-medium text-gray-700">{{ __('purchases.fields.invoice_date') }}</label>
                        <input id="draft-invoice-date" type="date" wire:model="invoiceDate" max="{{ now()->toDateString() }}" @error('invoiceDate') aria-invalid="true" aria-describedby="draft-invoice-date-error" @enderror @class([$field, $invalid => $errors->has('invoiceDate')])>
                        @error('invoiceDate') <p id="draft-invoice-date-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="draft-due-date" class="block text-sm font-medium text-gray-700">{{ __('purchases.fields.due_date') }} <span class="font-normal text-gray-500">({{ __('purchases.form.optional') }})</span></label>
                        <input id="draft-due-date" type="date" wire:model="dueDate" x-bind:min="$wire.invoiceDate || null" @error('dueDate') aria-invalid="true" aria-describedby="draft-due-date-error" @enderror @class([$field, $invalid => $errors->has('dueDate')])>
                        @error('dueDate') <p id="draft-due-date-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>

            <section aria-labelledby="draft-lines-title" class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                <div class="space-y-3 border-b border-gray-100 p-5">
                    <h2 id="draft-lines-title" class="text-base font-semibold text-gray-900">{{ __('purchases.draft.lines_title') }}</h2>
                    <div>
                        <label for="draft-product-search" class="block text-sm font-medium text-gray-700">{{ __('purchases.draft.find_product') }}</label>
                        <div class="relative">
                            <input id="draft-product-search" type="search" wire:model.live.debounce.300ms="productSearch" wire:keydown.enter.prevent="addExactMatch" autocomplete="off" spellcheck="false" placeholder="{{ __('purchases.draft.find_product_placeholder') }}" aria-describedby="draft-product-search-help @error('productSearch') draft-product-search-error @enderror" @error('productSearch') aria-invalid="true" @enderror @class([$field, $invalid => $errors->has('productSearch')])>
                            <div wire:loading.flex wire:target="productSearch,addExactMatch,addProduct" class="pointer-events-none absolute inset-y-0 end-3 mt-1 items-center" aria-hidden="true">
                                <svg class="h-4 w-4 animate-spin text-gray-400" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"></path></svg>
                            </div>
                        </div>
                        <p id="draft-product-search-help" class="mt-1 text-xs text-gray-500">{{ __('purchases.draft.find_product_help') }}</p>
                        @error('productSearch') <p id="draft-product-search-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    @if (mb_strlen(trim($productSearch)) >= 2)
                        @if ($productMatches->isEmpty())
                            <p class="rounded-lg bg-gray-50 px-3 py-3 text-sm text-gray-600" role="status">{{ __('purchases.draft.no_product_matches') }}</p>
                        @else
                            <ul class="divide-y divide-gray-100 rounded-lg ring-1 ring-gray-200">
                                @foreach ($productMatches as $match)
                                    <li wire:key="product-match-{{ $match->id }}">
                                        <button type="button" wire:click="addProduct({{ $match->id }})" class="flex min-h-11 w-full flex-wrap items-center gap-x-3 gap-y-1 px-3 py-2 text-start hover:bg-blue-50 focus-visible:bg-blue-50">
                                            <span class="font-medium text-gray-900">{{ $match->name }}</span>
                                            @if ($match->model)<bdi class="text-sm text-gray-500">{{ $match->model }}</bdi>@endif
                                            <span class="text-xs text-gray-500">{{ $match->tracking_mode->label() }}</span>
                                            @if ($match->sku)<span dir="ltr" class="ms-auto font-mono text-xs text-gray-500">{{ $match->sku }}</span>@endif
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    @endif
                </div>

                @error('lines')
                    <p role="alert" class="mx-5 mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800">{{ $message }}</p>
                @enderror

                @if ($lines === [])
                    <p class="px-5 py-10 text-center text-sm text-gray-500">{{ __('purchases.draft.no_lines') }}</p>
                @else
                    <ol class="divide-y divide-gray-100">
                        @foreach ($lines as $index => $line)
                            @php
                                $definition = $definitions->get((int) $line['product_definition_id']);
                            @endphp
                            <li wire:key="draft-line-{{ $index }}-{{ $line['product_definition_id'] }}" class="grid gap-3 px-5 py-4 md:grid-cols-12 md:items-start">
                                <div class="md:col-span-5">
                                    <p class="text-sm font-medium text-gray-900"><span class="text-gray-400">{{ $index + 1 }}.</span> {{ $definition?->name ?? '#'.$line['product_definition_id'] }}</p>
                                    <p class="text-xs text-gray-500">
                                        @if ($definition?->model)<bdi>{{ $definition->model }}</bdi> · @endif
                                        {{ $definition?->tracking_mode->label() }}
                                        @if ($definition?->sku) · <span dir="ltr" class="font-mono">{{ $definition->sku }}</span>@endif
                                    </p>
                                    @if ($definition?->tracking_mode->tracksIndividualItems())
                                        <p class="mt-1 text-xs text-amber-800">{{ __('purchases.help.quantity_tracked') }}</p>
                                    @endif
                                    @error("lines.$index.product_definition_id") <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div class="md:col-span-2">
                                    <label for="line-{{ $index }}-quantity" class="block text-xs font-medium text-gray-600">{{ __('purchases.fields.quantity') }}</label>
                                    <input id="line-{{ $index }}-quantity" type="text" inputmode="numeric" wire:model="lines.{{ $index }}.quantity" dir="ltr" autocomplete="off" @error("lines.$index.quantity") aria-invalid="true" aria-describedby="line-{{ $index }}-quantity-error" @enderror @class([$field, 'text-end tabular-nums', $invalid => $errors->has("lines.$index.quantity")])>
                                    @error("lines.$index.quantity") <p id="line-{{ $index }}-quantity-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div class="md:col-span-3">
                                    <label for="line-{{ $index }}-cost" class="block text-xs font-medium text-gray-600">{{ __('purchases.fields.unit_cost') }}</label>
                                    <div class="relative" dir="ltr">
                                        <input id="line-{{ $index }}-cost" type="text" inputmode="decimal" wire:model="lines.{{ $index }}.unit_cost" dir="ltr" autocomplete="off"
                                            x-on:purchase-line-added.window="if ($event.detail.index === {{ $index }}) $nextTick(() => $el.focus())"
                                            aria-describedby="draft-unit-cost-help @error("lines.$index.unit_cost") line-{{ $index }}-cost-error @enderror" @error("lines.$index.unit_cost") aria-invalid="true" @enderror
                                            @class([$field, 'pe-14 text-end tabular-nums', $invalid => $errors->has("lines.$index.unit_cost")])>
                                        <span class="pointer-events-none absolute inset-y-0 end-3 mt-1 flex items-center text-xs font-medium text-gray-500" x-text="$wire.currency">{{ $currencyCode }}</span>
                                    </div>
                                    @error("lines.$index.unit_cost") <p id="line-{{ $index }}-cost-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div class="flex items-start justify-between gap-2 md:col-span-2 md:flex-col md:items-end">
                                    <span class="text-xs font-medium text-gray-600 md:hidden">{{ __('purchases.fields.line_total') }}</span>
                                    <span class="pt-1 text-sm font-medium text-gray-900 md:pt-7">
                                        @if ($lineTotals[$index] !== null)
                                            @include('livewire.backend.purchases.partials.money', ['minor' => $lineTotals[$index], 'currency' => $currencyCode])
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </span>
                                    <button type="button" wire:click="removeLine({{ $index }})" wire:loading.attr="disabled" wire:target="removeLine" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg text-gray-500 hover:bg-red-50 hover:text-red-700" aria-label="{{ __('purchases.draft.remove_line', ['line' => $index + 1]) }}">
                                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.44c-.79.08-1.57.18-2.34.31a.75.75 0 0 0 .25 1.48l.15-.02.95 10.41A2.75 2.75 0 0 0 7.75 19h4.5a2.75 2.75 0 0 0 2.74-2.63l.95-10.41.15.02a.75.75 0 0 0 .25-1.48A41 41 0 0 0 14 4.19v-.44A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.67.03 2.5.08v-.33c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.33C8.33 4.03 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd"/></svg>
                                    </button>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                    <p id="draft-unit-cost-help" class="border-t border-gray-100 px-5 py-3 text-xs text-gray-500">{{ __('purchases.help.unit_cost') }}</p>
                @endif
            </section>

            <section aria-labelledby="draft-charges-title" class="grid gap-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200 sm:grid-cols-2">
                <h2 id="draft-charges-title" class="sr-only">{{ __('purchases.fields.additional_charges') }}</h2>
                <div>
                    <label for="draft-charges" class="block text-sm font-medium text-gray-700">{{ __('purchases.fields.additional_charges') }} <span class="font-normal text-gray-500">({{ __('purchases.form.optional') }})</span></label>
                    <div class="relative" dir="ltr">
                        <input id="draft-charges" type="text" inputmode="decimal" wire:model="additionalCharges" dir="ltr" autocomplete="off" aria-describedby="draft-charges-help @error('additionalCharges') draft-charges-error @enderror" @error('additionalCharges') aria-invalid="true" @enderror @class([$field, 'pe-14 text-end tabular-nums', $invalid => $errors->has('additionalCharges')])>
                        <span class="pointer-events-none absolute inset-y-0 end-3 mt-1 flex items-center text-xs font-medium text-gray-500" x-text="$wire.currency">{{ $currencyCode }}</span>
                    </div>
                    <p id="draft-charges-help" class="mt-1 text-xs text-gray-500">{{ __('purchases.help.additional_charges') }}</p>
                    @error('additionalCharges') <p id="draft-charges-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="draft-charges-note" class="block text-sm font-medium text-gray-700">{{ __('purchases.fields.additional_charges_note') }} <span class="font-normal text-gray-500">({{ __('purchases.form.optional') }})</span></label>
                    <input id="draft-charges-note" type="text" wire:model="chargesNote" maxlength="255" class="{{ $field }}">
                    @error('chargesNote') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label for="draft-notes" class="block text-sm font-medium text-gray-700">{{ __('purchases.fields.notes') }} <span class="font-normal text-gray-500">({{ __('purchases.form.optional') }})</span></label>
                    <textarea id="draft-notes" rows="2" wire:model="notes" maxlength="2000" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                    @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </section>
        </div>

        <aside class="xl:col-span-1">
            <div class="space-y-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200 xl:sticky xl:top-4">
                <div class="flex items-baseline justify-between gap-2">
                    <h2 class="text-base font-semibold text-gray-900">{{ __('purchases.draft.last_saved') }}</h2>
                    <span class="text-xs text-gray-500">{{ __('purchases.draft.revision', ['revision' => $revision]) }}</span>
                </div>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-gray-600">{{ __('purchases.fields.items_total') }}</dt><dd>@include('livewire.backend.purchases.partials.money', ['minor' => $purchase->itemsTotalMinor(), 'currency' => $purchase->currency])</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-600">{{ __('purchases.fields.additional_charges') }}</dt><dd>@include('livewire.backend.purchases.partials.money', ['minor' => $purchase->additionalChargesMinor(), 'currency' => $purchase->currency])</dd></div>
                    <div class="flex justify-between gap-3 border-t border-gray-100 pt-2 text-base font-semibold"><dt class="text-gray-900">{{ __('purchases.fields.total') }}</dt><dd>@include('livewire.backend.purchases.partials.money', ['minor' => $purchase->totalMinor(), 'currency' => $purchase->currency])</dd></div>
                </dl>
                @if ($previewTotal !== null && $previewTotal !== $purchase->totalMinor())
                    <p class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900" role="status">
                        {{ __('purchases.draft.preview_total') }}: @include('livewire.backend.purchases.partials.money', ['minor' => $previewTotal, 'currency' => $currencyCode, 'class' => 'font-semibold'])
                        <span class="block">{{ __('purchases.draft.unsaved_hint') }}</span>
                    </p>
                @endif

                <div class="space-y-2 border-t border-gray-100 pt-4">
                    <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save,reviewPosting,post" class="min-h-11 w-full rounded-lg px-4 py-2 text-sm font-medium text-gray-800 ring-1 ring-gray-300 hover:bg-gray-50 disabled:opacity-60">
                        <span wire:loading.remove wire:target="save">{{ __('purchases.draft.save') }}</span>
                        <span wire:loading wire:target="save">{{ __('purchases.draft.saving') }}</span>
                    </button>
                    <button type="button" wire:click="reviewPosting" wire:loading.attr="disabled" wire:target="save,reviewPosting,post" class="min-h-11 w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60">
                        <span wire:loading.remove wire:target="reviewPosting">{{ __('purchases.draft.post') }}</span>
                        <span wire:loading wire:target="reviewPosting">{{ __('purchases.draft.reviewing') }}</span>
                    </button>
                    <button type="button" wire:click="cancelDraft" wire:confirm="{{ __('purchases.draft.cancel_confirm') }}" wire:loading.attr="disabled" wire:target="cancelDraft" class="min-h-11 w-full rounded-lg px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50 disabled:opacity-60">{{ __('purchases.draft.cancel') }}</button>
                </div>
            </div>
        </aside>
    </div>

    <x-operations.dialog id="post-review" :title="__('purchases.post.title')" :direction="$textDirection">
        <div class="space-y-4">
            <p class="text-sm text-gray-700">{!! __('purchases.post.body', [
                'total' => view('livewire.backend.purchases.partials.money', ['minor' => $purchase->totalMinor(), 'currency' => $purchase->currency, 'class' => 'font-semibold text-gray-900'])->render(),
                'supplier' => '<bdi class="font-semibold">'.e($purchase->supplier->name).'</bdi>',
            ]) !!}</p>
            <dl class="grid grid-cols-2 gap-3 rounded-lg bg-gray-50 p-3 text-sm">
                <div><dt class="text-xs text-gray-500">{{ __('purchases.fields.invoice_number') }}</dt><dd dir="ltr" class="text-start font-mono">{{ $purchase->supplier_invoice_number ?? '—' }}</dd></div>
                <div><dt class="text-xs text-gray-500">{{ __('purchases.fields.invoice_date') }}</dt><dd dir="ltr" class="text-start">{{ $purchase->invoice_date?->format('Y-m-d') ?? '—' }}</dd></div>
                <div><dt class="text-xs text-gray-500">{{ __('purchases.fields.lines') }}</dt><dd>{{ __('purchases.post.lines', ['count' => count($lines)]) }}</dd></div>
                <div><dt class="text-xs text-gray-500">{{ __('purchases.fields.tax_treatment') }}</dt><dd>{{ $purchase->tax_treatment->label() }}</dd></div>
            </dl>
            <div class="flex flex-col-reverse gap-2 border-t border-gray-100 pt-4 sm:flex-row sm:justify-end">
                <button type="button" x-on:click="$el.closest('dialog').close()" class="min-h-11 rounded-lg px-4 py-2 text-sm font-medium text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50">{{ __('purchases.post.back') }}</button>
                <button type="button" wire:click="post" wire:loading.attr="disabled" wire:target="post" class="min-h-11 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60">
                    <span wire:loading.remove wire:target="post">{{ __('purchases.post.confirm') }}</span>
                    <span wire:loading wire:target="post">{{ __('purchases.post.posting') }}</span>
                </button>
            </div>
        </div>
    </x-operations.dialog>
</div>
