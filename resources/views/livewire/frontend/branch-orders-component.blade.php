<div class="mx-auto max-w-4xl px-4 py-8 sm:px-6">
    <h1 class="text-2xl font-bold text-gray-900">{{ __('main.branch_orders.title') }}</h1>

    @if (session('success'))
        <p class="mt-4 rounded-xl bg-green-50 p-4 text-sm text-green-800 ring-1 ring-green-200" role="status">{{ session('success') }}</p>
    @endif

    @error('order')
        <p class="mt-4 rounded-xl bg-red-50 p-4 text-sm text-red-800 ring-1 ring-red-200" role="alert">{{ $message }}</p>
    @enderror

    <div class="mt-6 space-y-4">
        @forelse ($orders as $order)
            @php($status = $order->displayStatus())
            <article wire:key="branch-order-{{ $order->id }}" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-xs" data-testid="branch-order">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-gray-900">{{ __('main.branch_orders.number', ['number' => $order->order_id]) }}</h2>
                        <p class="text-sm text-gray-600">{{ __('main.branch_orders.pickup_at', ['branch' => $order->store->name.' ('.$order->store->code.')']) }}</p>
                        <p class="text-sm text-gray-600">{{ $order->shop?->name }}</p>
                    </div>
                    <span @class([
                        'rounded-full px-3 py-1 text-sm font-semibold',
                        'bg-amber-100 text-amber-900' => $status === \App\Enums\StorefrontOrderStatus::PENDING,
                        'bg-green-100 text-green-900' => $status === \App\Enums\StorefrontOrderStatus::CONFIRMED,
                        'bg-blue-100 text-blue-900' => $status === \App\Enums\StorefrontOrderStatus::FULFILLED,
                        'bg-gray-100 text-gray-800' => in_array($status, [\App\Enums\StorefrontOrderStatus::CANCELLED, \App\Enums\StorefrontOrderStatus::EXPIRED], true),
                        'bg-red-100 text-red-900' => $status === \App\Enums\StorefrontOrderStatus::REJECTED,
                    ])>{{ $status->label() }}</span>
                </div>

                <ul class="mt-4 divide-y divide-gray-100 text-sm">
                    @foreach ($order->lines as $line)
                        <li class="flex justify-between gap-4 py-2">
                            <span class="text-gray-800">{{ $line->product?->name }} × {{ $line->quantity }}</span>
                            <span class="text-end">
                                <bdi dir="ltr" class="tabular-nums text-gray-900">{{ \App\Services\Purchasing\LocalizedMoney::format($line->unitPriceMinor()) }} TL</bdi>
                                @if ($order->awaitsPriceAcceptance() && $line->proposedUnitPriceMinor() !== null && $line->proposedUnitPriceMinor() !== $line->unitPriceMinor())
                                    <span class="block font-semibold text-amber-800">{{ __('main.branch_orders.proposed_price', ['price' => \App\Services\Purchasing\LocalizedMoney::format($line->proposedUnitPriceMinor()).' TL']) }}</span>
                                @endif
                                @if ($order->awaitsPriceAcceptance() && $line->proposedUnit)
                                    <span class="block text-gray-600" data-testid="proposed-device">{{ __('main.branch_orders.proposed_device', ['condition' => $line->proposedUnit->condition->label()]) }}</span>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>

                <div class="mt-3 flex justify-between border-t border-gray-200 pt-3 font-semibold text-gray-900">
                    <span>{{ __('main.branch_orders.total') }}</span>
                    <bdi dir="ltr" class="tabular-nums">{{ \App\Services\Purchasing\LocalizedMoney::format($order->totalMinor()) }} TL</bdi>
                </div>

                @if ($status === \App\Enums\StorefrontOrderStatus::PENDING)
                    <p class="mt-3 text-sm text-gray-600">{{ $order->awaitsPriceAcceptance() ? __('main.branch_orders.price_change_help') : __('main.branch_orders.pending_help') }}</p>
                @elseif ($status === \App\Enums\StorefrontOrderStatus::CONFIRMED)
                    <p class="mt-3 text-sm text-gray-700">{{ __('main.branch_orders.confirmed_help', ['until' => $order->expires_at?->format('d.m.Y H:i')]) }}</p>
                @elseif ($status === \App\Enums\StorefrontOrderStatus::CANCELLED && $order->cancellation_reason)
                    <p class="mt-3 text-sm text-gray-700">{{ __('main.branch_orders.cancel_reason', ['reason' => $order->cancellation_reason]) }}</p>
                @elseif ($status === \App\Enums\StorefrontOrderStatus::REJECTED)
                    <p class="mt-3 text-sm text-gray-700" data-testid="rejection-reason">{{ __('main.branch_orders.rejection_reason', ['reason' => $order->rejection_reason]) }}</p>
                @endif

                @if ($status === \App\Enums\StorefrontOrderStatus::PENDING)
                    <div class="mt-4 flex flex-wrap gap-3">
                        @if ($order->awaitsPriceAcceptance())
                            <button type="button" wire:click="acceptPrices({{ $order->id }}, '{{ $order->price_proposal_fingerprint }}')" wire:loading.attr="disabled" class="min-h-11 rounded-xl bg-indigo-600 px-4 font-semibold text-white hover:bg-indigo-700 focus:outline-hidden focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">{{ __('main.branch_orders.accept_prices') }}</button>
                        @endif
                        <button type="button" wire:click="cancel({{ $order->id }})" wire:confirm="{{ __('main.branch_orders.cancel_confirm') }}" wire:loading.attr="disabled" class="min-h-11 rounded-xl border border-gray-300 px-4 font-semibold text-gray-700 hover:bg-gray-50 focus:outline-hidden focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">{{ __('main.branch_orders.cancel') }}</button>
                    </div>
                @endif
            </article>
        @empty
            <p class="rounded-2xl bg-white p-8 text-center text-gray-600 shadow-xs">{{ __('main.branch_orders.empty') }}</p>
        @endforelse
    </div>

    <div class="mt-6">{{ $orders->links() }}</div>
</div>
