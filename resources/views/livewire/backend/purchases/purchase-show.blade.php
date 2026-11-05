<div dir="{{ $textDirection }}" lang="{{ $locale }}" class="mx-auto max-w-6xl space-y-6 text-start">
    @php
        $field = 'mt-1 block min-h-11 w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500';
        $invalid = 'border-red-400 focus:border-red-500 focus:ring-red-500';
        $money = 'livewire.backend.purchases.partials.money';
        $outstanding = $purchase->outstandingMinor();
        $supplierName = $purchase->supplier_name ?? $purchase->supplier->name;
        $title = $purchase->supplier_invoice_number ?? __('purchases.index.draft_label', ['id' => $purchase->id]);
        $canRecordPayment = $canPay && $purchase->isPosted() && $outstanding > 0;
        $canReversePurchase = $canUpdate && $purchase->isPosted() && ! $purchase->isReceived() && $activePaid === 0;
        $canCopy = $canUpdate && $purchase->status === \App\Enums\PurchaseStatus::REVERSED;
        $openCorrection = $purchase->corrections->first(fn ($correction) => $correction->isDraft());
        // Left-to-right isolate so dates and codes keep their order inside Arabic sentences.
        $ltr = fn (?string $value): string => "\u{2066}".$value."\u{2069}";
    @endphp

    @include('livewire.backend.inventory.partials.breadcrumbs', ['items' => [
        [__('purchases.title'), route('subdomain.purchases')],
        [$title, null],
    ]])

    <header class="space-y-3">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div class="space-y-1">
                <h1 class="flex flex-wrap items-center gap-3 text-2xl font-bold text-gray-900">
                    <bdi dir="ltr" @class(['font-mono' => $purchase->supplier_invoice_number])>{{ $title }}</bdi>
                    @include('livewire.backend.purchases.partials.badges', ['purchase' => $purchase])
                </h1>
                <p class="text-sm text-gray-600">
                    <bdi class="font-medium text-gray-900">{{ $supplierName }}</bdi>
                    @if ($canViewAccount)
                        · <a href="{{ route('subdomain.suppliers.account', $purchase->supplier) }}" wire:navigate class="text-blue-700 underline hover:no-underline">{{ __('purchases.show.supplier_account') }}</a>
                    @endif
                    · <span dir="ltr" class="font-mono">{{ $purchase->store->code }}</span> {{ $purchase->store->name }}
                </p>
                @if ($purchase->poster && $purchase->posted_at)
                    <p class="text-xs text-gray-500">{{ __('purchases.show.posted_by', ['name' => $purchase->poster->name, 'date' => $ltr($purchase->posted_at->format('Y-m-d H:i'))]) }}</p>
                @endif
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($canReceive && $purchase->isPosted() && ! $purchase->isReceived())
                    <a href="{{ route('subdomain.purchases.receive', $purchase) }}" wire:navigate class="inline-flex min-h-11 items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">{{ __('purchases.receipt.receive') }}</a>
                @endif
                @if ($canRecordPayment)
                    <button type="button" x-on:click="$dispatch('open-dialog', { id: 'record-payment' })" @class([
                        'inline-flex min-h-11 items-center rounded-lg px-4 py-2 text-sm font-medium',
                        'bg-blue-600 text-white hover:bg-blue-700' => $purchase->isReceived(),
                        'text-gray-800 ring-1 ring-gray-300 hover:bg-gray-50' => ! $purchase->isReceived(),
                    ])>{{ __('purchases.payments.record') }}</button>
                @endif
                @if ($canReversePurchase)
                    <button type="button" x-on:click="$dispatch('open-dialog', { id: 'reverse-purchase' })" class="inline-flex min-h-11 items-center rounded-lg px-4 py-2 text-sm font-medium text-red-700 ring-1 ring-red-200 hover:bg-red-50">{{ __('purchases.reverse.action') }}</button>
                @endif
                @if ($canCopy)
                    @if ($openCorrection)
                        <a href="{{ route('subdomain.purchases.edit', $openCorrection) }}" wire:navigate class="inline-flex min-h-11 items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">{{ __('purchases.show.open_correction') }}</a>
                    @else
                        <button type="button" wire:click="copyAsCorrection" wire:loading.attr="disabled" wire:target="copyAsCorrection" class="inline-flex min-h-11 items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60">{{ __('purchases.copy.action') }}</button>
                    @endif
                @endif
            </div>
        </div>

        @error('reverseReason')
            <div role="alert" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200">{{ $message }}</div>
        @enderror

        @if ($purchase->status === \App\Enums\PurchaseStatus::REVERSED)
            <div class="rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-900 ring-1 ring-rose-200">
                <p class="font-medium">{{ __('purchases.show.reversed_by', ['name' => $purchase->reverser?->name ?? '—', 'date' => $ltr($purchase->reversed_at?->format('Y-m-d H:i'))]) }}</p>
                <p class="mt-1"><span class="font-medium">{{ __('purchases.show.reversal_reason') }}:</span> <bdi>{{ $purchase->reversal_reason }}</bdi></p>
            </div>
        @elseif ($purchase->status === \App\Enums\PurchaseStatus::CANCELLED)
            <p class="rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-700 ring-1 ring-gray-200">{{ __('purchases.show.cancelled_by', ['name' => $purchase->canceller?->name ?? '—', 'date' => $ltr($purchase->cancelled_at?->format('Y-m-d H:i'))]) }}</p>
        @endif

        @if ($purchase->correctionOf)
            <p class="text-sm text-gray-600">{{ __('purchases.show.correction_of') }}:
                <a href="{{ route('subdomain.purchases.show', $purchase->correctionOf) }}" wire:navigate class="text-blue-700 underline hover:no-underline"><bdi dir="ltr" class="font-mono">{{ $purchase->correctionOf->supplier_invoice_number }}</bdi></a>
            </p>
        @endif
        @if ($purchase->corrections->isNotEmpty())
            <p class="text-sm text-gray-600">{{ __('purchases.show.corrections') }}:
                @foreach ($purchase->corrections as $correction)
                    <a href="{{ route('subdomain.purchases.show', $correction) }}" wire:navigate class="text-blue-700 underline hover:no-underline">{{ $correction->supplier_invoice_number ?? __('purchases.index.draft_label', ['id' => $correction->id]) }}</a> ({{ $correction->status->label() }})@if (! $loop->last), @endif
                @endforeach
            </p>
        @endif
    </header>

    <div class="grid gap-6 xl:grid-cols-3">
        <section aria-labelledby="lines-title" class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 xl:col-span-2">
            <h2 id="lines-title" class="border-b border-gray-100 px-5 py-4 text-base font-semibold text-gray-900">{{ __('purchases.show.lines_title') }}</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-2 text-start text-xs font-semibold text-gray-600">{{ __('purchases.fields.product') }}</th>
                            <th scope="col" class="px-4 py-2 text-end text-xs font-semibold text-gray-600">{{ __('purchases.fields.quantity') }}</th>
                            <th scope="col" class="px-4 py-2 text-end text-xs font-semibold text-gray-600">{{ __('purchases.fields.unit_cost') }}</th>
                            <th scope="col" class="px-4 py-2 text-end text-xs font-semibold text-gray-600">{{ __('purchases.fields.line_total') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($purchase->lines as $line)
                            <tr wire:key="line-{{ $line->id }}">
                                <td class="px-4 py-3">
                                    <p class="text-sm font-medium text-gray-900">{{ $line->product_name }}</p>
                                    <p class="text-xs text-gray-500">
                                        @if ($line->product_model)<bdi>{{ $line->product_model }}</bdi> · @endif
                                        {{ $line->tracking_mode->label() }}
                                        @if ($line->product_sku) · <span dir="ltr" class="font-mono">{{ $line->product_sku }}</span>@endif
                                        @if ($canViewInventory && $purchase->isReceived())
                                            · <a href="{{ route('subdomain.inventory.product', [$purchase->store_id, $line->product_definition_id]) }}" wire:navigate class="text-blue-700 underline hover:no-underline">{{ __('purchases.receipt.view_stock') }}</a>
                                        @endif
                                    </p>
                                </td>
                                <td class="px-4 py-3 text-end text-sm tabular-nums text-gray-900">{{ $line->quantity }}</td>
                                <td class="px-4 py-3 text-end text-sm">@include($money, ['minor' => $line->unitCostMinor(), 'currency' => $purchase->currency])</td>
                                <td class="px-4 py-3 text-end text-sm font-medium">@include($money, ['minor' => $line->lineTotalMinor(), 'currency' => $purchase->currency])</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($purchase->notes)
                <p class="border-t border-gray-100 px-5 py-3 text-sm text-gray-600"><span class="font-medium">{{ __('purchases.fields.notes') }}:</span> <bdi>{{ $purchase->notes }}</bdi></p>
            @endif
        </section>

        <aside class="space-y-6">
            <section aria-labelledby="summary-title" class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                <h2 id="summary-title" class="text-base font-semibold text-gray-900">{{ __('purchases.show.summary') }}</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-gray-600">{{ __('purchases.fields.items_total') }}</dt><dd>@include($money, ['minor' => $purchase->itemsTotalMinor(), 'currency' => $purchase->currency])</dd></div>
                    @if ($purchase->additionalChargesMinor() > 0)
                        <div class="flex justify-between gap-3"><dt class="text-gray-600">{{ __('purchases.fields.additional_charges') }}@if ($purchase->additional_charges_note)<span class="block text-xs text-gray-500"><bdi>{{ $purchase->additional_charges_note }}</bdi></span>@endif</dt><dd>@include($money, ['minor' => $purchase->additionalChargesMinor(), 'currency' => $purchase->currency])</dd></div>
                    @endif
                    <div class="flex justify-between gap-3 border-t border-gray-100 pt-2 font-semibold"><dt class="text-gray-900">{{ __('purchases.fields.total') }}</dt><dd>@include($money, ['minor' => $purchase->totalMinor(), 'currency' => $purchase->currency])</dd></div>
                    @if ($purchase->isPosted())
                        <div class="flex justify-between gap-3"><dt class="text-gray-600">{{ __('purchases.fields.paid_total') }}</dt><dd>@include($money, ['minor' => $purchase->paidTotalMinor(), 'currency' => $purchase->currency])</dd></div>
                        <div class="flex justify-between gap-3 rounded-lg bg-gray-50 px-3 py-2 text-base font-bold"><dt class="text-gray-900">{{ __('purchases.fields.outstanding') }}</dt><dd>@include($money, ['minor' => $outstanding, 'currency' => $purchase->currency])</dd></div>
                    @endif
                </dl>
                <dl class="mt-4 grid grid-cols-2 gap-3 border-t border-gray-100 pt-4 text-sm">
                    <div><dt class="text-xs text-gray-500">{{ __('purchases.fields.invoice_date') }}</dt><dd dir="ltr" class="text-start">{{ $purchase->invoice_date?->format('Y-m-d') ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-gray-500">{{ __('purchases.fields.due_date') }}</dt><dd dir="ltr" class="text-start">{{ $purchase->due_date?->format('Y-m-d') ?? '—' }}</dd></div>
                    <div class="col-span-2"><dt class="text-xs text-gray-500">{{ __('purchases.fields.tax_treatment') }}</dt><dd>{{ $purchase->tax_treatment->label() }}</dd></div>
                </dl>
                @if ($purchase->isPosted())
                    <p @class(['mt-4 rounded-lg px-3 py-2 text-xs', 'bg-emerald-50 text-emerald-900' => $isReconciled, 'bg-red-50 text-red-900' => ! $isReconciled]) @if (! $isReconciled) role="alert" @endif>
                        {{ $isReconciled ? __('purchases.show.reconciled') : __('purchases.show.not_reconciled') }}
                    </p>
                @endif
            </section>

            @if ($purchase->isPosted() || $purchase->isReceived())
                <section aria-labelledby="receipt-title" class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                    <h2 id="receipt-title" class="text-base font-semibold text-gray-900">{{ __('purchases.receipt.title') }}</h2>
                    @if ($purchase->receipt)
                        <p class="mt-2 text-sm text-gray-700">{{ __('purchases.receipt.received_body', ['store' => $ltr($purchase->store->code), 'name' => $purchase->receipt->receiver?->name ?? '—', 'date' => $ltr($purchase->receipt->received_at->format('Y-m-d H:i'))]) }}</p>
                        @if ($purchase->receipt->note)
                            <p class="mt-1 text-sm text-gray-600"><bdi>{{ $purchase->receipt->note }}</bdi></p>
                        @endif
                    @else
                        <p class="mt-2 text-sm text-gray-700">{{ __('purchases.receipt.pending_body', ['store' => $ltr($purchase->store->code)]) }}</p>
                    @endif
                </section>
            @endif
        </aside>
    </div>

    @if ($purchase->isPosted() || $purchase->payments->isNotEmpty())
        <section aria-labelledby="payments-title" class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
            <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-4">
                <h2 id="payments-title" class="text-base font-semibold text-gray-900">{{ __('purchases.payments.title') }}</h2>
                @if ($purchase->isPosted() && $outstanding === 0)
                    <span class="text-sm font-medium text-emerald-700">{{ __('purchases.payments.settled') }}</span>
                @endif
            </div>
            @if ($purchase->payments->isEmpty())
                <p class="px-5 py-8 text-center text-sm text-gray-500">{{ __('purchases.payments.empty') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 py-2 text-start text-xs font-semibold text-gray-600">{{ __('purchases.fields.paid_on') }}</th>
                                <th scope="col" class="px-4 py-2 text-start text-xs font-semibold text-gray-600">{{ __('purchases.fields.reference') }}</th>
                                <th scope="col" class="px-4 py-2 text-start text-xs font-semibold text-gray-600">{{ __('purchases.fields.method') }}</th>
                                <th scope="col" class="px-4 py-2 text-end text-xs font-semibold text-gray-600">{{ __('purchases.fields.amount') }}</th>
                                <th scope="col" class="px-4 py-2"><span class="sr-only">{{ __('purchases.payments.reverse') }}</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($purchase->payments as $payment)
                                <tr wire:key="payment-{{ $payment->id }}" @class(['bg-gray-50 text-gray-500' => $payment->isReversed()])>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm"><span dir="ltr">{{ $payment->paid_on->format('Y-m-d') }}</span>
                                        <span class="block text-xs text-gray-500">{{ __('purchases.payments.by', ['name' => $payment->payer?->name ?? '—']) }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <bdi dir="ltr" class="font-mono">{{ $payment->reference }}</bdi>
                                        @if ($payment->note)<span class="block text-xs text-gray-500"><bdi>{{ $payment->note }}</bdi></span>@endif
                                        @if ($payment->isReversed())
                                            <span class="mt-1 block text-xs text-rose-800">{{ __('purchases.payments.reversed_detail', ['name' => $payment->reverser?->name ?? '—', 'date' => $ltr($payment->reversed_at->format('Y-m-d H:i')), 'reason' => $payment->reversal_reason]) }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm">{{ $payment->method->label() }}</td>
                                    <td class="px-4 py-3 text-end text-sm">
                                        @include($money, ['minor' => $payment->amountMinor(), 'currency' => $payment->currency, 'class' => $payment->isReversed() ? 'line-through' : 'font-medium text-gray-900'])
                                        @if ($payment->isReversed())
                                            <span class="ms-1 inline-flex rounded-full bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-800 ring-1 ring-inset ring-rose-200">{{ __('purchases.payments.reversed_badge') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-end">
                                        @if ($canPay && ! $payment->isReversed() && $purchase->isPosted())
                                            <button type="button" x-on:click="$wire.reversingPaymentId = '{{ $payment->id }}'; $dispatch('open-dialog', { id: 'reverse-payment' })" class="min-h-11 rounded-lg px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50">{{ __('purchases.payments.reverse') }}</button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif

    @if ($purchase->ledgerEntries->isNotEmpty())
        <section aria-labelledby="ledger-title" class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
            <h2 id="ledger-title" class="border-b border-gray-100 px-5 py-4 text-base font-semibold text-gray-900">{{ __('purchases.show.ledger_title') }}</h2>
            <ul class="divide-y divide-gray-100">
                @foreach ($purchase->ledgerEntries as $entry)
                    <li wire:key="ledger-{{ $entry->id }}" class="flex flex-wrap items-center justify-between gap-2 px-5 py-3 text-sm">
                        <span>{{ $entry->type->label() }} <span dir="ltr" class="ms-2 text-xs text-gray-500">{{ $entry->occurred_at->format('Y-m-d H:i') }}</span></span>
                        @include($money, ['minor' => $entry->amountMinor(), 'currency' => $entry->currency, 'class' => $entry->amountMinor() < 0 ? 'text-emerald-700' : 'text-gray-900'])
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($canRecordPayment)
        <x-operations.dialog id="record-payment" :title="__('purchases.payments.record_title')" :direction="$textDirection">
            <form wire:submit="recordPayment" novalidate class="space-y-4">
                <p class="text-sm text-gray-600">{{ __('purchases.payments.record_help', ['currency' => $purchase->currency->label()]) }}</p>
                <div>
                    <label for="payment-amount" class="block text-sm font-medium text-gray-700">{{ __('purchases.fields.amount') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                    <div class="relative" dir="ltr">
                        <input id="payment-amount" type="text" inputmode="decimal" wire:model="paymentAmount" dir="ltr" autocomplete="off" autofocus required @error('paymentAmount') aria-invalid="true" aria-describedby="payment-amount-error" @enderror @class([$field, 'pe-14 text-end tabular-nums', $invalid => $errors->has('paymentAmount')])>
                        <span class="pointer-events-none absolute inset-y-0 end-3 mt-1 flex items-center text-xs font-medium text-gray-500">{{ $purchase->currency->value }}</span>
                    </div>
                    <button type="button" x-on:click="$wire.paymentAmount = @js(\App\Services\Purchasing\Money::input($outstanding))" class="mt-1 min-h-11 text-sm text-blue-700 underline hover:no-underline">{{ __('purchases.payments.pay_full', ['amount' => \App\Services\Purchasing\Money::format($outstanding).' '.$purchase->currency->value]) }}</button>
                    @error('paymentAmount') <p id="payment-amount-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="payment-date" class="block text-sm font-medium text-gray-700">{{ __('purchases.fields.paid_on') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                        <input id="payment-date" type="date" wire:model="paymentDate" required max="{{ now()->toDateString() }}" @error('paymentDate') aria-invalid="true" aria-describedby="payment-date-error" @enderror @class([$field, $invalid => $errors->has('paymentDate')])>
                        @error('paymentDate') <p id="payment-date-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="payment-method" class="block text-sm font-medium text-gray-700">{{ __('purchases.fields.method') }}</label>
                        <select id="payment-method" wire:model="paymentMethod" required class="{{ $field }}">
                            @foreach ($paymentMethods as $method)
                                <option value="{{ $method->value }}">{{ $method->label() }}</option>
                            @endforeach
                        </select>
                        @error('paymentMethod') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div>
                    <label for="payment-reference" class="block text-sm font-medium text-gray-700">{{ __('purchases.fields.reference') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                    <input id="payment-reference" type="text" wire:model="paymentReference" dir="ltr" maxlength="100" autocomplete="off" spellcheck="false" required aria-describedby="payment-reference-help @error('paymentReference') payment-reference-error @enderror" @error('paymentReference') aria-invalid="true" @enderror @class([$field, 'font-mono text-start', $invalid => $errors->has('paymentReference')])>
                    <p id="payment-reference-help" class="mt-1 text-xs text-gray-500">{{ __('purchases.help.reference') }}</p>
                    @error('paymentReference') <p id="payment-reference-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="payment-note" class="block text-sm font-medium text-gray-700">{{ __('purchases.fields.note') }} <span class="font-normal text-gray-500">({{ __('purchases.form.optional') }})</span></label>
                    <input id="payment-note" type="text" wire:model="paymentNote" maxlength="255" class="{{ $field }}">
                    @error('paymentNote') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex flex-col-reverse gap-2 border-t border-gray-100 pt-4 sm:flex-row sm:justify-end">
                    <button type="button" x-on:click="$el.closest('dialog').close()" class="min-h-11 rounded-lg px-4 py-2 text-sm font-medium text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50">{{ __('purchases.form.cancel') }}</button>
                    <button type="submit" wire:loading.attr="disabled" wire:target="recordPayment" class="min-h-11 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60">
                        <span wire:loading.remove wire:target="recordPayment">{{ __('purchases.payments.submit') }}</span>
                        <span wire:loading wire:target="recordPayment">{{ __('purchases.payments.submitting') }}</span>
                    </button>
                </div>
            </form>
        </x-operations.dialog>
    @endif

    @if ($canPay)
        <x-operations.dialog id="reverse-payment" :title="__('purchases.payments.reverse_title')" :direction="$textDirection">
            <form wire:submit="reversePayment" novalidate class="space-y-4">
                <p class="text-sm text-gray-600">{{ __('purchases.payments.reverse_help') }}</p>
                <div>
                    <label for="payment-reversal-reason" class="block text-sm font-medium text-gray-700">{{ __('purchases.fields.reason') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                    <textarea id="payment-reversal-reason" rows="3" autofocus wire:model="paymentReversalReason" maxlength="500" required @error('paymentReversalReason') aria-invalid="true" aria-describedby="payment-reversal-reason-error" @enderror @class(['mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500', $invalid => $errors->has('paymentReversalReason')])></textarea>
                    @error('paymentReversalReason') <p id="payment-reversal-reason-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex flex-col-reverse gap-2 border-t border-gray-100 pt-4 sm:flex-row sm:justify-end">
                    <button type="button" x-on:click="$el.closest('dialog').close()" class="min-h-11 rounded-lg px-4 py-2 text-sm font-medium text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50">{{ __('purchases.form.cancel') }}</button>
                    <button type="submit" wire:loading.attr="disabled" wire:target="reversePayment" class="min-h-11 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-60">{{ __('purchases.payments.reverse_submit') }}</button>
                </div>
            </form>
        </x-operations.dialog>
    @endif

    @if ($canReversePurchase)
        <x-operations.dialog id="reverse-purchase" :title="__('purchases.reverse.title')" :direction="$textDirection">
            <form wire:submit="reversePurchase" novalidate class="space-y-4">
                <p class="text-sm text-gray-600">{{ __('purchases.reverse.help') }}</p>
                <div>
                    <label for="purchase-reverse-reason" class="block text-sm font-medium text-gray-700">{{ __('purchases.fields.reason') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                    <textarea id="purchase-reverse-reason" rows="3" autofocus wire:model="reverseReason" maxlength="500" required @error('reverseReason') aria-invalid="true" aria-describedby="purchase-reverse-reason-error" @enderror @class(['mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500', $invalid => $errors->has('reverseReason')])></textarea>
                    @error('reverseReason') <p id="purchase-reverse-reason-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex flex-col-reverse gap-2 border-t border-gray-100 pt-4 sm:flex-row sm:justify-end">
                    <button type="button" x-on:click="$el.closest('dialog').close()" class="min-h-11 rounded-lg px-4 py-2 text-sm font-medium text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50">{{ __('purchases.form.cancel') }}</button>
                    <button type="submit" wire:loading.attr="disabled" wire:target="reversePurchase" class="min-h-11 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-60">{{ __('purchases.reverse.submit') }}</button>
                </div>
            </form>
        </x-operations.dialog>
    @endif
</div>
