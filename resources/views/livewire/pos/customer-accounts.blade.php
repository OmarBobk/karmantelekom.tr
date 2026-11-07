@php
    use App\Services\Purchasing\LocalizedMoney;
    use App\Services\Purchasing\Money;
@endphp
<section dir="{{ $textDirection }}" lang="{{ $locale }}" aria-labelledby="customer-accounts-title" class="space-y-4 text-start">
    <div>
        <h1 id="customer-accounts-title" class="text-xl font-semibold text-gray-900">{{ __('pos.customer_accounts.title') }}</h1>
        <p class="text-sm text-gray-600">{{ $settled ? __('pos.customer_accounts.help_settled') : __('pos.customer_accounts.help') }}</p>
    </div>

    <div role="group" aria-label="{{ __('pos.customer_accounts.balance_filter') }}" class="inline-flex rounded-lg bg-white p-1 shadow-sm ring-1 ring-gray-200">
        @foreach (['open' => __('pos.customer_accounts.filter_open'), 'settled' => __('pos.customer_accounts.filter_settled')] as $value => $label)
            <button
                type="button"
                wire:click="showBalance('{{ $value }}')"
                aria-pressed="{{ ($settled ? 'settled' : 'open') === $value ? 'true' : 'false' }}"
                @class([
                    'inline-flex min-h-11 items-center rounded-md px-4 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600',
                    'bg-blue-600 text-white' => ($settled ? 'settled' : 'open') === $value,
                    'text-gray-700 hover:bg-gray-100' => ($settled ? 'settled' : 'open') !== $value,
                ])
            >{{ $label }}</button>
        @endforeach
    </div>

    <form wire:submit="applySearch" role="search" class="flex flex-wrap items-end gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
        <div class="min-w-0 flex-1 basis-60">
            <label for="customer-accounts-search" class="block text-sm font-medium text-gray-800">{{ __('pos.customer_accounts.search') }}</label>
            <input id="customer-accounts-search" type="search" dir="auto" wire:model="search" maxlength="80" autocomplete="off" class="mt-1 block min-h-11 w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
        </div>
        <button type="submit" class="inline-flex min-h-11 items-center rounded-lg bg-blue-600 px-4 text-sm font-semibold text-white hover:bg-blue-700">{{ __('pos.history.apply') }}</button>
        @if ($search !== '')
            <button type="button" wire:click="clearSearch" class="inline-flex min-h-11 items-center rounded-lg px-4 text-sm font-semibold text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50">{{ __('pos.history.clear') }}</button>
        @endif
    </form>

    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200" wire:loading.class="opacity-60">
        @if ($debtors->isEmpty())
            <p class="px-4 py-8 text-center text-sm text-gray-600">
                @if ($search !== '')
                    {{ $settled ? __('pos.customer_accounts.no_settled_matches', ['term' => $search]) : __('pos.customer_accounts.no_matches', ['term' => $search]) }}
                @else
                    {{ $settled ? __('pos.customer_accounts.empty_settled') : __('pos.customer_accounts.empty') }}
                @endif
            </p>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach ($debtors as $debtor)
                    <li wire:key="debtor-{{ $debtor->id }}">
                        <a href="{{ route('pos.customers.show', [$store, $debtor]) }}" class="flex min-h-14 flex-wrap items-center gap-x-4 gap-y-1 px-4 py-3 hover:bg-gray-50 focus-visible:bg-blue-50 focus-visible:outline-none">
                            <span class="font-medium text-gray-900">{{ $debtor->name }}</span>
                            <bdi dir="ltr" class="font-mono text-sm text-gray-600">{{ $debtor->maskedPhone() }}</bdi>
                            @if ($settled)
                                <span class="ms-auto rounded bg-emerald-50 px-1.5 py-0.5 text-xs font-medium text-emerald-800 ring-1 ring-emerald-200">{{ __('pos.customer_accounts.settled') }}</span>
                            @else
                                <span class="ms-auto text-sm text-gray-600">{{ __('pos.customer_accounts.total_due') }}</span>
                                <span dir="ltr" class="font-semibold tabular-nums text-gray-900">{{ LocalizedMoney::format(Money::fromDecimal($debtor->due_amount)) }} TRY</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{ $debtors->links() }}
</section>
