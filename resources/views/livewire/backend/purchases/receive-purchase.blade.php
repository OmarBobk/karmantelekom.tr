<div dir="{{ $textDirection }}" lang="{{ $locale }}" class="mx-auto max-w-5xl space-y-6 text-start">
    @php
        $field = 'mt-1 block min-h-11 w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500';
        $invalid = 'border-red-400 focus:border-red-500 focus:ring-red-500';
        $invoiceLabel = $purchase->supplier_invoice_number ?? __('purchases.index.draft_label', ['id' => $purchase->id]);
    @endphp

    @include('livewire.backend.inventory.partials.breadcrumbs', ['items' => [
        [__('purchases.title'), route('subdomain.purchases')],
        [$invoiceLabel, route('subdomain.purchases.show', $purchase)],
        [__('purchases.receipt.page_title'), null],
    ]])

    <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ __('purchases.receipt.page_title') }}</h1>
        <p class="mt-1 text-sm text-gray-600">
            <bdi dir="ltr" class="font-mono">{{ $invoiceLabel }}</bdi> · <bdi>{{ $purchase->supplier_name ?? $purchase->supplier->name }}</bdi> · <span dir="ltr" class="font-mono">{{ $purchase->store->code }}</span> {{ $purchase->store->name }}
        </p>
    </div>

    @if (! $canReceive)
        <div role="status" class="rounded-xl bg-white p-6 text-sm text-gray-700 shadow-sm ring-1 ring-gray-200">
            <p>
                @if ($purchase->receipt)
                    {{ __('purchases.receipt.received_body', ['store' => "\u{2066}".$purchase->store->code."\u{2069}", 'name' => $purchase->receipt->receiver?->name ?? '—', 'date' => "\u{2066}".$purchase->receipt->received_at->format('Y-m-d H:i')."\u{2069}"]) }}
                @else
                    {{ __('purchases.receipt.not_receivable') }}
                @endif
            </p>
            <a href="{{ route('subdomain.purchases.show', $purchase) }}" wire:navigate class="mt-3 inline-flex min-h-11 items-center text-blue-700 underline hover:no-underline">{{ $invoiceLabel }}</a>
        </div>
    @else
        <form wire:submit="receive" wire:confirm="{{ __('purchases.receipt.confirm') }}" novalidate class="space-y-6"
            x-data
            x-on:keydown.enter="if ($event.target.matches('[data-scan]')) { $event.preventDefault(); const fields = [...$root.querySelectorAll('[data-scan]')]; fields[fields.indexOf($event.target) + 1]?.focus(); }">
            <p class="rounded-lg bg-blue-50 px-4 py-3 text-sm text-blue-900 ring-1 ring-blue-100">{{ __('purchases.receipt.page_help') }}</p>

            @error('receipt')
                <div role="alert" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200">{{ $message }}</div>
            @enderror

            @foreach ($purchase->lines as $line)
                <section wire:key="receive-line-{{ $line->id }}" aria-labelledby="receive-line-{{ $line->id }}-title" class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-gray-100 px-5 py-4">
                        <h2 id="receive-line-{{ $line->id }}-title" class="text-base font-semibold text-gray-900">
                            <span class="text-gray-400">{{ $line->line_number }}.</span> {{ $line->product_name }}
                            @if ($line->product_model)<bdi class="text-sm font-normal text-gray-500">{{ $line->product_model }}</bdi>@endif
                        </h2>
                        <span class="text-sm text-gray-600">{{ $line->tracking_mode->label() }} · <span class="tabular-nums">× {{ $line->quantity }}</span></span>
                    </div>
                    @error("lines.{$line->id}")
                        <p role="alert" class="mx-5 mt-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800">{{ $message }}</p>
                    @enderror

                    @if (! $line->tracking_mode->tracksIndividualItems())
                        <p class="px-5 py-4 text-sm text-gray-700">{{ __('purchases.receipt.quantity_line', ['quantity' => $line->quantity, 'store' => $purchase->store->code]) }}</p>
                    @else
                        <p class="px-5 pt-4 text-sm text-gray-700">{{ __('purchases.receipt.units_line', ['count' => $line->quantity]) }} <span class="text-gray-500">{{ __('inventory.register.identifier_help') }}</span></p>
                        <ol class="divide-y divide-gray-100">
                            @foreach ($units[$line->id] ?? [] as $i => $unit)
                                @php
                                    $prefix = "units.{$line->id}.{$i}";
                                @endphp
                                <li wire:key="unit-{{ $line->id }}-{{ $i }}" class="grid gap-3 px-5 py-4 sm:grid-cols-2 lg:grid-cols-4">
                                    <p class="text-sm font-medium text-gray-700 sm:col-span-2 lg:col-span-4">{{ __('purchases.receipt.unit_label', ['number' => $i + 1]) }}</p>
                                    <div>
                                        <label for="unit-{{ $line->id }}-{{ $i }}-condition" class="block text-xs font-medium text-gray-600">{{ __('inventory.fields.condition') }}</label>
                                        @if (count($conditionsByLine[$line->id]) === 1)
                                            <p id="unit-{{ $line->id }}-{{ $i }}-condition" class="mt-1 flex min-h-11 items-center text-sm text-gray-900">{{ $conditionsByLine[$line->id][0]->label() }}</p>
                                        @else
                                            <select id="unit-{{ $line->id }}-{{ $i }}-condition" wire:model="{{ $prefix }}.condition" required @error("$prefix.condition") aria-invalid="true" aria-describedby="unit-{{ $line->id }}-{{ $i }}-condition-error" @enderror @class([$field, $invalid => $errors->has("$prefix.condition")])>
                                                <option value="">{{ __('purchases.form.choose') }}</option>
                                                @foreach ($conditionsByLine[$line->id] as $condition)
                                                    <option value="{{ $condition->value }}">{{ $condition->label() }}</option>
                                                @endforeach
                                            </select>
                                        @endif
                                        @error("$prefix.condition") <p id="unit-{{ $line->id }}-{{ $i }}-condition-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                    @foreach ([
                                        ['imei', __('inventory.fields.imei'), 'numeric', 15],
                                        ['secondary_imei', __('inventory.fields.secondary_imei'), 'numeric', 15],
                                        ['serial', __('inventory.fields.serial'), 'text', 64],
                                    ] as [$property, $label, $inputMode, $maxLength])
                                        @php
                                            $inputId = "unit-{$line->id}-{$i}-{$property}";
                                        @endphp
                                        <div>
                                            <label for="{{ $inputId }}" class="block text-xs font-medium text-gray-600">{{ $label }}</label>
                                            <input id="{{ $inputId }}" type="text" inputmode="{{ $inputMode }}" maxlength="{{ $maxLength }}" dir="ltr" autocomplete="off" spellcheck="false" data-scan wire:model="{{ $prefix }}.{{ $property }}" @error("$prefix.$property") aria-invalid="true" aria-describedby="{{ $inputId }}-error" @enderror @class([$field, 'font-mono tracking-wide', $invalid => $errors->has("$prefix.$property")])>
                                            @error("$prefix.$property") <p id="{{ $inputId }}-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                        </div>
                                    @endforeach
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </section>
            @endforeach

            <div class="space-y-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                <div>
                    <label for="receipt-note" class="block text-sm font-medium text-gray-700">{{ __('purchases.receipt.note') }} <span class="font-normal text-gray-500">({{ __('purchases.form.optional') }})</span></label>
                    <textarea id="receipt-note" rows="2" wire:model="note" maxlength="1000" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                    @error('note') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <a href="{{ route('subdomain.purchases.show', $purchase) }}" wire:navigate class="inline-flex min-h-11 items-center justify-center rounded-lg px-4 py-2 text-sm font-medium text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50">{{ __('purchases.form.cancel') }}</a>
                    <button type="submit" wire:loading.attr="disabled" wire:target="receive" class="min-h-11 rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60">
                        <span wire:loading.remove wire:target="receive">{{ __('purchases.receipt.submit') }}</span>
                        <span wire:loading wire:target="receive">{{ __('purchases.receipt.submitting') }}</span>
                    </button>
                </div>
            </div>
        </form>
    @endif
</div>
