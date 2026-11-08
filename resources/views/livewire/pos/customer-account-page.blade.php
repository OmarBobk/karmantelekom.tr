@php
    use App\Models\Sale;
    use App\Services\Purchasing\LocalizedMoney;
    use App\Services\Purchasing\Money;
    $inputClass = 'block min-h-11 w-full rounded-lg border-gray-300 text-base shadow-sm focus:border-blue-500 focus:ring-blue-500';
@endphp
<section dir="{{ $textDirection }}" lang="{{ $locale }}" aria-labelledby="customer-account-title" class="space-y-4 text-start">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <a href="{{ route('pos.customers', $store) }}" class="text-sm font-medium text-blue-700 underline">{{ __('pos.customer_accounts.back') }}</a>
            <h1 id="customer-account-title" class="mt-1 text-xl font-semibold text-gray-900">
                {{ $customer->name }} <bdi dir="ltr" class="ms-1 font-mono text-base font-normal text-gray-600">{{ $customer->maskedPhone() }}</bdi>
            </h1>
        </div>
        <div class="text-end">
            <p class="text-sm text-gray-600">{{ __('pos.customer_accounts.total_due') }}</p>
            <p dir="ltr" class="text-2xl font-bold tabular-nums text-gray-900">{{ LocalizedMoney::format($totalDue) }} TRY</p>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-12">
        <div class="space-y-4 lg:col-span-7">
            <section aria-labelledby="open-debts-title" class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                <h2 id="open-debts-title" class="border-b border-gray-100 px-4 py-3 text-base font-semibold text-gray-900">{{ __('pos.customer_accounts.open_debts') }}</h2>
                @if ($openDebts->isEmpty())
                    <p class="px-4 py-6 text-center text-sm text-gray-600">{{ __('pos.customer_accounts.no_open_debts') }}</p>
                @else
                    <p class="px-4 pt-2 text-xs text-gray-600">{{ __('pos.customer_accounts.allocation_help') }}</p>
                    <table class="w-full text-sm">
                        <thead class="text-xs text-gray-600">
                            <tr>
                                <th scope="col" class="px-4 py-2 text-start font-medium">{{ __('pos.fields.sale_number') }}</th>
                                <th scope="col" class="px-4 py-2 text-end font-medium">{{ __('pos.customer_accounts.original') }}</th>
                                <th scope="col" class="px-4 py-2 text-end font-medium">{{ __('pos.customer_accounts.paid_since') }}</th>
                                <th scope="col" class="px-4 py-2 text-end font-medium">{{ __('pos.customer_accounts.outstanding') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($openDebts as $debt)
                                <tr wire:key="debt-{{ $debt->id }}">
                                    <td class="px-4 py-2">
                                        <a href="{{ route('pos.sales.show', [$store, $debt->sale_id]) }}" dir="ltr" class="font-mono font-semibold text-blue-700 underline">{{ $debt->sale->number }}</a>
                                        <time dir="ltr" datetime="{{ $debt->sale->completed_at->toIso8601String() }}" class="block text-xs text-gray-600">{{ Sale::displayTime($debt->sale->completed_at) }}</time>
                                    </td>
                                    <td dir="ltr" class="px-4 py-2 text-end tabular-nums">{{ LocalizedMoney::format($debt->originalAmountMinor()) }}</td>
                                    <td dir="ltr" class="px-4 py-2 text-end tabular-nums">{{ LocalizedMoney::format($debt->originalAmountMinor() - $debt->outstandingMinor()) }}</td>
                                    <td dir="ltr" class="px-4 py-2 text-end font-semibold tabular-nums text-gray-900">{{ LocalizedMoney::format($debt->outstandingMinor()) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </section>

            <section aria-labelledby="payment-history-title" class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                <h2 id="payment-history-title" class="border-b border-gray-100 px-4 py-3 text-base font-semibold text-gray-900">{{ __('pos.customer_accounts.history') }}</h2>
                @if ($payments->isEmpty())
                    <p class="px-4 py-6 text-center text-sm text-gray-600">{{ __('pos.customer_accounts.no_payments') }}</p>
                @else
                    <ul class="divide-y divide-gray-100">
                        @foreach ($payments as $payment)
                            <li wire:key="payment-{{ $payment->id }}" class="px-4 py-3 text-sm">
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                    <time dir="ltr" datetime="{{ $payment->received_at->toIso8601String() }}" class="text-gray-600">{{ Sale::displayTime($payment->received_at) }}</time>
                                    <span class="font-medium text-gray-900">{{ $payment->method->label() }}</span>
                                    <bdi dir="ltr" class="font-mono text-gray-700">{{ $payment->receipt_number ?? $payment->reference }}</bdi>
                                    <span class="text-gray-600">{{ $payment->receiver?->name }}</span>
                                    <span dir="ltr" class="ms-auto font-semibold tabular-nums text-gray-900">{{ LocalizedMoney::format($payment->amountMinor()) }} TRY</span>
                                </div>
                                <ul class="mt-1 text-xs text-gray-700">
                                    @foreach ($payment->allocations as $allocation)
                                        <li>{{ __('pos.customer_accounts.applied_to') }} <bdi dir="ltr" class="font-mono">{{ $allocation->debt->sale->number }}</bdi>: <span dir="ltr" class="tabular-nums">{{ LocalizedMoney::format($allocation->amountMinor()) }}</span></li>
                                    @endforeach
                                </ul>
                                @if ($payment->note)
                                    <p class="mt-1 text-xs text-gray-700">{{ $payment->note }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>

        <section aria-labelledby="record-payment-title" class="space-y-4 lg:col-span-5">
            @if ($cashIntake)
                <div wire:key="customer-cash-intake-{{ $cashIntake->id }}" class="rounded-xl bg-sky-50 p-4 text-sm text-sky-950 ring-2 ring-sky-500" role="status" aria-live="polite">
                    <p class="text-base font-semibold">{{ __('pos.cashbox.customer_intake_title', ['amount' => LocalizedMoney::format($cashIntake->amountMinor())]) }}</p>
                    <p class="mt-1">{{ __('pos.cashbox.customer_intake_detail') }}</p>
                    <p class="mt-1 text-xs">
                        <time dir="ltr" datetime="{{ $cashIntake->started_at->toIso8601String() }}">{{ Sale::displayTime($cashIntake->started_at) }}</time>
                        @can('manageCashbox', $store)
                            · <a href="{{ route('pos.cashbox', $store) }}" class="font-medium underline">{{ __('pos.nav.cashbox') }}</a>
                        @endcan
                    </p>
                    @error('cashbox') <p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                    <button type="button" wire:click="confirmCash" wire:loading.attr="disabled" wire:target="confirmCash" class="mt-3 inline-flex min-h-12 w-full items-center justify-center rounded-lg bg-emerald-600 px-5 text-base font-semibold text-white shadow-sm hover:bg-emerald-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 disabled:cursor-not-allowed disabled:opacity-60">
                        {{ __('pos.cashbox.customer_confirm') }}
                    </button>
                </div>
            @endif
            <form wire:submit="record" class="space-y-4 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200" x-data="{ method: $wire.entangle('method') }">
                <h2 id="record-payment-title" class="text-base font-semibold text-gray-900">{{ __('pos.customer_accounts.record_title') }}</h2>
                @if ($totalDue === 0)
                    <p class="rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-700 ring-1 ring-gray-200" role="status">{{ __('pos.customer_accounts.nothing_to_record') }}</p>
                @endif
                <fieldset class="min-w-0 space-y-4" @disabled($totalDue === 0)>
                    <x-operations.money-input
                        id="customer-payment-amount"
                        wire:model="amount"
                        error="amount"
                        :label="__('pos.customer_accounts.fields.amount')"
                        :placeholder="$totalDue > 0 ? LocalizedMoney::input($totalDue) : null"
                    />

                    <fieldset>
                        <legend class="block text-sm font-medium text-gray-800">{{ __('pos.customer_accounts.fields.method') }}</legend>
                        <div class="mt-1 grid gap-2 sm:grid-cols-3">
                            @foreach ($methods as $option)
                                <label wire:key="method-{{ $option->value }}" class="flex min-h-11 items-center gap-2 rounded-lg px-3 ring-1 ring-gray-200">
                                    <input type="radio" name="customer-payment-method" value="{{ $option->value }}" x-model="method" class="text-blue-600 focus:ring-blue-500">
                                    <span class="text-sm text-gray-900">{{ $option->label() }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('method') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                    </fieldset>

                    <p x-show="method === 'cash'" class="text-xs text-gray-600">{{ __('pos.customer_accounts.cash_help') }}</p>
                    @if ($cashTracked && ! $cashSessionOpen)
                        <p x-show="method === 'cash'" class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-950 ring-1 ring-amber-300">{{ __('pos.cashbox.customer_no_session') }}</p>
                    @endif
                    @unless ($cashIntake)
                        @error('cashbox') <p class="text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                    @endunless
                    <div x-show="method !== 'cash'" x-cloak>
                        <label for="customer-payment-reference" class="block text-sm font-medium text-gray-800">{{ __('pos.customer_accounts.fields.reference') }} <span class="text-red-700" aria-hidden="true">*</span></label>
                        <input id="customer-payment-reference" type="text" dir="ltr" autocomplete="off" maxlength="{{ \App\Models\CustomerPayment::MAX_REFERENCE_LENGTH }}" wire:model="reference" @error('reference') aria-invalid="true" aria-describedby="customer-payment-reference-error" @enderror class="{{ $inputClass }} mt-1 font-mono">
                        @error('reference') <p id="customer-payment-reference-error" class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="customer-payment-note" class="block text-sm font-medium text-gray-800">{{ __('pos.customer_accounts.fields.note') }}</label>
                        <textarea id="customer-payment-note" dir="auto" rows="2" maxlength="{{ \App\Models\CustomerPayment::MAX_NOTE_LENGTH }}" wire:model="note" class="{{ $inputClass }} mt-1"></textarea>
                        @error('note') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                    </div>

                    <button type="submit" wire:loading.attr="disabled" wire:target="record" class="inline-flex min-h-12 w-full items-center justify-center rounded-lg bg-emerald-600 px-5 text-base font-semibold text-white shadow-sm hover:bg-emerald-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 disabled:cursor-not-allowed disabled:opacity-60">
                        <span wire:loading.remove wire:target="record">
                            @if ($cashTracked)
                                <span x-show="method === 'cash'">{{ __('pos.cashbox.customer_start') }}</span>
                                <span x-show="method !== 'cash'" x-cloak>{{ __('pos.customer_accounts.record') }}</span>
                            @else
                                {{ __('pos.customer_accounts.record') }}
                            @endif
                        </span>
                        <span wire:loading wire:target="record">{{ __('pos.customer_accounts.recording') }}</span>
                    </button>
                </fieldset>
            </form>
        </section>
    </div>
</section>
