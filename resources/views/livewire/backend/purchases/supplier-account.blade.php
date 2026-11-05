<div dir="{{ $textDirection }}" lang="{{ $locale }}" class="mx-auto max-w-6xl space-y-6 text-start">
    @php($money = 'livewire.backend.purchases.partials.money')

    @include('livewire.backend.inventory.partials.breadcrumbs', ['items' => [
        [__('suppliers.title'), route('subdomain.suppliers')],
        [$supplier->name, null],
    ]])

    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900"><bdi>{{ $supplier->name }}</bdi></h1>
            <p class="mt-1 text-sm text-gray-500">{{ __('purchases.account.subtitle') }}</p>
        </div>
        @can('viewAny', \App\Models\Purchase::class)
            <a href="{{ route('subdomain.purchases', ['search' => $supplier->name]) }}" wire:navigate class="inline-flex min-h-11 items-center text-sm font-medium text-blue-700 underline hover:no-underline">{{ __('purchases.account.view_purchases') }}</a>
        @endcan
    </div>

    @if ($balances === [])
        <div class="rounded-xl border-2 border-dashed border-gray-300 bg-white px-6 py-12 text-center">
            <p class="text-sm text-gray-600">{{ __('purchases.account.empty') }}</p>
        </div>
    @else
        <div class="grid gap-4 md:grid-cols-2">
            @foreach ($balances as $code => $totals)
                @php($reconciled = $totals['balance'] === $totals['open_invoices'])
                <section wire:key="balance-{{ $code }}" aria-labelledby="balance-{{ $code }}-title" class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                    <h2 id="balance-{{ $code }}-title" class="text-sm font-medium text-gray-600">{{ __('purchases.fields.balance') }} · {{ \App\Enums\Currency::from($code)->label() }}</h2>
                    <p class="mt-2 text-2xl font-bold text-gray-900">@include($money, ['minor' => $totals['balance'], 'currency' => $code])</p>
                    <p @class(['mt-2 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset', 'bg-emerald-50 text-emerald-800 ring-emerald-200' => $reconciled, 'bg-red-50 text-red-800 ring-red-200' => ! $reconciled]) @if (! $reconciled) role="alert" @endif>
                        {{ $reconciled ? __('purchases.account.reconciled') : __('purchases.account.not_reconciled') }}
                        @unless ($reconciled)
                            (@include($money, ['minor' => $totals['open_invoices'], 'currency' => $code]))
                        @endunless
                    </p>
                    @if (count($totals['stores']) > 0)
                        <h3 class="mt-4 text-xs font-semibold text-gray-500">{{ __('purchases.account.by_store') }}</h3>
                        <dl class="mt-1 divide-y divide-gray-100 text-sm">
                            @foreach ($totals['stores'] as $storeId => $storeTotals)
                                <div class="flex justify-between gap-3 py-1.5">
                                    <dt><span dir="ltr" class="font-mono">{{ $stores->get($storeId)?->code }}</span> <span class="text-gray-500">{{ $stores->get($storeId)?->name }}</span></dt>
                                    <dd>@include($money, ['minor' => $storeTotals['balance'], 'currency' => $code])</dd>
                                </div>
                            @endforeach
                        </dl>
                    @endif
                </section>
            @endforeach
        </div>

        <section aria-labelledby="statement-title" class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-4">
                <h2 id="statement-title" class="text-base font-semibold text-gray-900">{{ __('purchases.account.statement') }}</h2>
                @if (count($currencies) > 1)
                    <div role="radiogroup" aria-label="{{ __('purchases.fields.currency') }}" class="inline-flex rounded-lg bg-gray-100 p-1">
                        @foreach ($currencies as $currencyOption)
                            <label @class([
                                'flex min-h-9 min-w-14 cursor-pointer items-center justify-center rounded-md px-3 text-sm font-medium',
                                'bg-white text-gray-900 shadow-sm' => $selectedCurrency === $currencyOption,
                                'text-gray-600 hover:text-gray-900' => $selectedCurrency !== $currencyOption,
                            ])>
                                <input type="radio" wire:model.live="currency" value="{{ $currencyOption->value }}" class="sr-only" @checked($selectedCurrency === $currencyOption)>
                                {{ $currencyOption->value }}
                            </label>
                        @endforeach
                    </div>
                @endif
            </div>

            @if ($entries->isEmpty())
                <p class="px-5 py-10 text-center text-sm text-gray-500">{{ __('purchases.account.statement_empty') }}</p>
            @else
                <div class="overflow-x-auto" wire:loading.class="opacity-60" wire:target="currency,gotoPage,nextPage,previousPage">
                    <table class="min-w-full divide-y divide-gray-100">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 py-2 text-start text-xs font-semibold text-gray-600">{{ __('purchases.fields.date') }}</th>
                                <th scope="col" class="px-4 py-2 text-start text-xs font-semibold text-gray-600">{{ __('purchases.fields.entry') }}</th>
                                <th scope="col" class="px-4 py-2 text-start text-xs font-semibold text-gray-600">{{ __('purchases.fields.invoice_number') }}</th>
                                <th scope="col" class="px-4 py-2 text-end text-xs font-semibold text-gray-600">{{ __('purchases.fields.amount') }}</th>
                                <th scope="col" class="px-4 py-2 text-end text-xs font-semibold text-gray-600">{{ __('purchases.fields.running_balance') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($entries as $entry)
                                <tr wire:key="entry-{{ $entry->id }}">
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700"><span dir="ltr">{{ $entry->occurred_at->format('Y-m-d H:i') }}</span>
                                        <span class="block text-xs text-gray-500"><span dir="ltr" class="font-mono">{{ $entry->store?->code }}</span> · {{ $entry->actor?->name }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ $entry->type->label() }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        <a href="{{ route('subdomain.purchases.show', $entry->purchase_id) }}" wire:navigate class="text-blue-700 underline hover:no-underline"><bdi dir="ltr" class="font-mono">{{ $entry->purchase?->supplier_invoice_number ?? '#'.$entry->purchase_id }}</bdi></a>
                                    </td>
                                    <td class="px-4 py-3 text-end text-sm">@include($money, ['minor' => $entry->amountMinor(), 'currency' => $entry->currency, 'class' => $entry->amountMinor() < 0 ? 'text-emerald-700' : 'text-gray-900'])</td>
                                    <td class="px-4 py-3 text-end text-sm font-medium">@include($money, ['minor' => \App\Services\Purchasing\Money::fromDecimal((string) $entry->running_balance), 'currency' => $entry->currency])</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-gray-100 px-5 py-3">{{ $entries->links() }}</div>
            @endif
        </section>
    @endif
</div>
