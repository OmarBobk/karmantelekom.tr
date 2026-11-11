@php
    use App\Services\Purchasing\LocalizedMoney;
@endphp
<div dir="{{ $textDirection }}" lang="{{ $locale }}" class="mx-auto max-w-6xl space-y-6 text-start">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            @if ($branch)
                <a href="{{ route('subdomain.operations.dashboard', ['from' => $from, 'to' => $to]) }}" class="text-sm font-medium text-blue-600 hover:underline">{{ __('reports.all_branches') }}</a>
                <h1 class="mt-1 flex flex-wrap items-center gap-2 text-2xl font-bold text-gray-900">
                    {{ $branch->name }}
                    <span dir="ltr" class="rounded-md bg-gray-100 px-2 py-0.5 font-mono text-sm text-gray-700">{{ $branch->code }}</span>
                </h1>
                <p class="mt-1 text-sm text-gray-500">{{ __('reports.branch_subtitle') }}</p>
            @else
                <h1 class="text-2xl font-bold text-gray-900">{{ __('reports.title') }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ __('reports.subtitle') }}</p>
            @endif
        </div>
        @if ($canViewFinance)
            <a href="{{ route('subdomain.operations.finance', ['from' => $from, 'to' => $to]) }}" class="inline-flex min-h-11 items-center rounded-lg px-3 text-sm font-medium text-blue-700 ring-1 ring-blue-200 hover:bg-blue-50">{{ __('reports.open_finance') }}</a>
        @endif
    </div>

    @if ($rows->isEmpty())
        <div class="rounded-xl border-2 border-dashed border-gray-300 bg-white px-6 py-12 text-center">
            <h2 class="text-base font-semibold text-gray-900">{{ __('reports.empty_title') }}</h2>
            <p class="mt-1 text-sm text-gray-500">{{ __('reports.empty_body') }}</p>
        </div>
    @else
        <form wire:submit="applyRange" class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
            <fieldset>
                <legend class="text-sm font-semibold text-gray-900">{{ __('reports.range.legend') }}</legend>
                <div class="mt-2 flex flex-wrap items-end gap-3">
                    <div>
                        <label for="dashboard-from" class="block text-sm font-medium text-gray-800">{{ __('reports.range.from') }}</label>
                        <input id="dashboard-from" type="date" wire:model="from" dir="ltr" required @if ($rangeError) aria-invalid="true" aria-describedby="dashboard-range-error" @endif class="mt-1 block min-h-11 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label for="dashboard-to" class="block text-sm font-medium text-gray-800">{{ __('reports.range.to') }}</label>
                        <input id="dashboard-to" type="date" wire:model="to" dir="ltr" required @if ($rangeError) aria-invalid="true" aria-describedby="dashboard-range-error" @endif class="mt-1 block min-h-11 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <button type="submit" wire:loading.attr="disabled" class="inline-flex min-h-11 items-center rounded-lg bg-blue-600 px-4 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">{{ __('reports.range.apply') }}</button>
                    <button type="button" wire:click="showToday" wire:loading.attr="disabled" class="inline-flex min-h-11 items-center rounded-lg px-4 text-sm font-semibold text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50 disabled:opacity-60">{{ __('reports.range.today') }}</button>
                    <span wire:loading class="text-sm text-gray-500" role="status">{{ __('reports.range.loading') }}</span>
                </div>
                @if ($rangeError)
                    <p id="dashboard-range-error" role="alert" class="mt-2 text-sm font-medium text-red-700">{{ __('reports.range.'.$rangeError) }}</p>
                @endif
            </fieldset>
        </form>

        <section aria-labelledby="dashboard-sales-title" class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200" wire:loading.class="opacity-60">
            <div class="border-b border-gray-100 px-4 py-3">
                <h2 id="dashboard-sales-title" class="text-base font-semibold text-gray-900">{{ __('reports.sales.title') }}</h2>
                @unless ($rangeError)
                    <p class="mt-1 text-sm text-gray-600">{!! __('reports.sales.period', ['from' => '<bdi dir="ltr">'.e($from).'</bdi>', 'to' => '<bdi dir="ltr">'.e($to).'</bdi>']) !!}</p>
                @endunless
                <p class="mt-1 text-xs text-gray-500">{{ __('reports.sales.scope_note') }}</p>
            </div>

            @if ($rangeError)
                <p class="px-4 py-8 text-center text-sm text-gray-600">{{ __('reports.sales.unavailable') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-xs text-gray-600">
                            <tr>
                                <th scope="col" class="px-4 py-2 text-start font-medium">{{ __('reports.sales.branch') }}</th>
                                <th scope="col" class="px-4 py-2 text-end font-medium">{{ __('reports.sales.count') }}</th>
                                <th scope="col" class="px-4 py-2 text-end font-medium">{{ __('reports.sales.total') }}</th>
                                <th scope="col" class="px-4 py-2"><span class="sr-only">{{ __('reports.sales.view_sales') }}</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($rows as $row)
                                <tr wire:key="dashboard-sales-{{ $row['store']->id }}">
                                    <th scope="row" class="px-4 py-3 text-start font-medium text-gray-900">
                                        @if ($branch)
                                            {{ $row['store']->name }}
                                        @else
                                            <a href="{{ route('subdomain.operations.dashboard.store', [$row['store'], 'from' => $from, 'to' => $to]) }}" aria-label="{{ __('reports.sales.open_branch_label', ['branch' => $row['store']->code]) }}" class="text-blue-700 hover:underline">{{ $row['store']->name }}</a>
                                        @endif
                                        <span dir="ltr" class="ms-1 font-mono text-xs text-gray-500">{{ $row['store']->code }}</span>
                                    </th>
                                    <td dir="ltr" class="px-4 py-3 text-end font-mono tabular-nums text-gray-900">{{ $row['count'] }}</td>
                                    <td class="px-4 py-3 text-end font-semibold text-gray-900"><span dir="ltr" class="tabular-nums">{{ LocalizedMoney::format($row['total_minor']) }} TRY</span></td>
                                    <td class="px-4 py-3 text-end">
                                        <a href="{{ route('pos.sales', [$row['store'], 'from' => $from, 'to' => $to]) }}" aria-label="{{ __('reports.sales.view_sales_label', ['branch' => $row['store']->code]) }}" class="inline-flex min-h-11 items-center rounded-lg px-3 text-sm font-medium text-blue-700 hover:bg-blue-50">{{ __('reports.sales.view_sales') }}</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        @unless ($branch)
                            <tfoot class="border-t-2 border-gray-200 bg-gray-50">
                                <tr>
                                    <th scope="row" class="px-4 py-3 text-start font-semibold text-gray-900">{{ __('reports.all_branches') }}</th>
                                    <td dir="ltr" class="px-4 py-3 text-end font-mono font-semibold tabular-nums text-gray-900">{{ $ownerCount }}</td>
                                    <td class="px-4 py-3 text-end font-bold text-gray-900"><span dir="ltr" class="tabular-nums">{{ LocalizedMoney::format($ownerTotalMinor) }} TRY</span></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        @endunless
                    </table>
                </div>
            @endif
        </section>

        @if ($inventory->isNotEmpty())
            <section aria-labelledby="dashboard-inventory-title" class="space-y-3">
                <div>
                    <h2 id="dashboard-inventory-title" class="text-base font-semibold text-gray-900">{{ __('reports.inventory.title') }}</h2>
                    <p class="text-sm text-gray-600">{{ __('reports.inventory.note') }}</p>
                </div>
                <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($inventory as $store)
                        <li wire:key="dashboard-inventory-{{ $store->id }}">
                            <a href="{{ route('subdomain.inventory.store', $store) }}" aria-label="{{ __('reports.inventory.open_label', ['branch' => $store->code]) }}" class="block h-full rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200 transition hover:ring-blue-300 focus:outline-hidden focus:ring-2 focus:ring-blue-500">
                                <div class="flex items-start justify-between gap-3">
                                    <p class="font-semibold text-gray-900">{{ $store->name }}</p>
                                    <span dir="ltr" class="rounded-md bg-gray-100 px-2 py-0.5 font-mono text-xs text-gray-700">{{ $store->code }}</span>
                                </div>
                                <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                                    <div>
                                        <dt class="text-xs text-gray-500">{{ __('reports.inventory.quantity_on_hand') }}</dt>
                                        <dd dir="ltr" class="text-start font-mono text-lg font-semibold tabular-nums text-gray-900">{{ (int) $store->quantity_on_hand }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs text-gray-500">{{ __('reports.inventory.available_units') }}</dt>
                                        <dd dir="ltr" class="text-start font-mono text-lg font-semibold tabular-nums text-gray-900">{{ $store->available_units_count }}</dd>
                                    </div>
                                </dl>
                                <p class="mt-4 text-sm font-medium text-blue-600">{{ __('reports.inventory.open') }}</p>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    @endif
</div>
