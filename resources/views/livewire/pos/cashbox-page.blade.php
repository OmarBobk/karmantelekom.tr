@php
    use App\Livewire\Pos\CashboxPage;
    use App\Models\Sale;
    use App\Services\Purchasing\LocalizedMoney;
    use Illuminate\Support\Carbon;
    $inputClass = 'block min-h-11 w-full rounded-lg border-gray-300 text-base shadow-sm focus:border-blue-500 focus:ring-blue-500';
    $primaryButton = 'inline-flex min-h-12 w-full items-center justify-center rounded-lg px-5 text-base font-semibold text-white shadow-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 disabled:cursor-not-allowed disabled:opacity-60';
    $secondaryButton = 'inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-white px-4 text-sm font-semibold text-gray-800 shadow-sm ring-1 ring-gray-300 hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600';
    $signed = fn (int $minor): string => ($minor > 0 ? '+' : '').LocalizedMoney::format($minor);
@endphp
<section dir="{{ $textDirection }}" lang="{{ $locale }}" aria-labelledby="cashbox-title" class="space-y-4 text-start">
    <h1 id="cashbox-title" class="text-xl font-semibold text-gray-900">{{ __('pos.cashbox.heading') }}</h1>

    @error('cashbox')
        <p class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800 ring-1 ring-red-200" role="alert">{{ $message }}</p>
    @enderror

    <div class="grid gap-4 lg:grid-cols-12">
        @if ($session === null)
            <div class="space-y-4 lg:col-span-7">
                <p class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-950 ring-1 ring-amber-300" role="status">
                    {{ $tracked ? __('pos.cashbox.no_session') : __('pos.cashbox.not_tracked') }}
                </p>
            </div>

            <section aria-labelledby="cashbox-open-title" class="lg:col-span-5">
                <form wire:submit="open" class="space-y-4 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <h2 id="cashbox-open-title" class="text-base font-semibold text-gray-900">{{ __('pos.cashbox.open_title') }}</h2>
                    <p class="text-sm text-gray-600">{{ __('pos.cashbox.open_help') }}</p>
                    @if ($previous)
                        <p class="text-sm text-gray-700">{{ __('pos.cashbox.previous_count', ['amount' => LocalizedMoney::format($previous->countedClosingMinor())]) }}</p>
                    @endif
                    <x-operations.money-input id="cashbox-opening-counted" wire:model="openingCounted" error="opening_counted" :label="__('pos.cashbox.opening_counted')" />
                    <div>
                        <label for="cashbox-opening-note" class="block text-sm font-medium text-gray-800">{{ __('pos.cashbox.opening_note') }}</label>
                        <p id="cashbox-opening-note-help" class="text-xs text-gray-600">{{ __('pos.cashbox.opening_note_help') }}</p>
                        <textarea id="cashbox-opening-note" dir="auto" rows="2" maxlength="{{ \App\Models\CashboxSession::MAX_NOTE_LENGTH }}" wire:model="openingNote" aria-describedby="cashbox-opening-note-help" class="{{ $inputClass }} mt-1"></textarea>
                        @error('opening_note') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <button type="submit" wire:loading.attr="disabled" wire:target="open" class="{{ $primaryButton }} bg-blue-600 hover:bg-blue-700">{{ __('pos.cashbox.open') }}</button>
                </form>
            </section>
        @else
            <div class="space-y-4 lg:col-span-7">
                <section aria-labelledby="cashbox-current-title" class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <h2 id="cashbox-current-title" class="text-base font-semibold text-gray-900">{{ __('pos.cashbox.current_title') }}</h2>
                            <p class="text-sm text-gray-600">{{ __('pos.cashbox.opened_by', ['name' => $session->opener?->name, 'time' => Sale::displayTime($session->opened_at)]) }}</p>
                        </div>
                        <button type="button" wire:click="refreshExpected" wire:loading.attr="disabled" wire:target="refreshExpected" class="{{ $secondaryButton }}">{{ __('pos.cashbox.refresh') }}</button>
                    </div>
                    <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                        <dt class="text-gray-600">{{ __('pos.cashbox.opening') }}</dt>
                        <dd dir="ltr" class="text-end tabular-nums">{{ LocalizedMoney::format($session->openingCountedMinor()) }}</dd>
                        <dt class="text-gray-600">{{ __('pos.cashbox.cash_in') }}</dt>
                        <dd dir="ltr" class="text-end tabular-nums">{{ $signed($cashIn) }}</dd>
                        <dt class="text-gray-600">{{ __('pos.cashbox.cash_out') }}</dt>
                        <dd dir="ltr" class="text-end tabular-nums">{{ $signed(-$cashOut) }}</dd>
                        <dt class="font-semibold text-gray-900">{{ __('pos.cashbox.expected') }}</dt>
                        <dd dir="ltr" class="text-end text-xl font-bold tabular-nums text-gray-900">{{ LocalizedMoney::format((int) $expectedShown) }} TRY</dd>
                    </dl>
                    @if ($expectedShownAt)
                        <p class="mt-1 text-end text-xs text-gray-500">{{ __('pos.cashbox.expected_as_of', ['time' => Sale::displayTime(Carbon::parse($expectedShownAt))]) }}</p>
                    @endif
                </section>

                @if ($pendingPayoutCount > 0)
                    <p class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-950 ring-1 ring-amber-300" role="status">
                        {{ trans_choice('pos.cashbox.pending_payouts', $pendingPayoutCount, ['count' => $pendingPayoutCount]) }}
                        @can('manageExpenses', $store)
                            <a href="{{ route('pos.expenses', $store) }}" class="font-medium underline">{{ __('pos.nav.expenses') }}</a>
                        @endcan
                    </p>
                @endif

                @if ($pendingIntakes->isNotEmpty())
                    <section aria-labelledby="cashbox-pending-title" class="rounded-xl bg-white shadow-sm ring-1 ring-amber-300">
                        <h2 id="cashbox-pending-title" class="border-b border-amber-100 px-4 py-3 text-base font-semibold text-gray-900">{{ __('pos.cashbox.pending_title') }}</h2>
                        <p class="px-4 pt-2 text-xs text-gray-600">{{ __('pos.cashbox.pending_help') }}</p>
                        <ul class="divide-y divide-gray-100">
                            @foreach ($pendingIntakes as $intake)
                                <li wire:key="intake-{{ $intake->id }}" class="flex flex-wrap items-center gap-3 px-4 py-3 text-sm">
                                    <div class="min-w-0 flex-1">
                                        <p class="font-medium text-gray-900">
                                            {{ $intake->customer_id
                                                ? __('pos.cashbox.pending_customer', ['customer' => $intake->customer?->name])
                                                : __('pos.cashbox.pending_sale', ['name' => $intake->starter?->name]) }}
                                        </p>
                                        <time dir="ltr" datetime="{{ $intake->started_at->toIso8601String() }}" class="text-xs text-gray-600">{{ Sale::displayTime($intake->started_at) }}</time>
                                    </div>
                                    <span dir="ltr" class="font-semibold tabular-nums text-gray-900">{{ LocalizedMoney::format($intake->amountMinor()) }} TRY</span>
                                    <button type="button" wire:click="openRelease({{ $intake->id }})" class="{{ $secondaryButton }} text-red-700">{{ __('pos.cashbox.release') }}</button>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                @if ($pendingTransfers->isNotEmpty())
                    <section aria-labelledby="cashbox-transfers-pending-title" class="rounded-xl bg-amber-50 shadow-sm ring-2 ring-amber-400">
                        <h2 id="cashbox-transfers-pending-title" class="border-b border-amber-200 px-4 py-3 text-base font-semibold text-amber-950">{{ __('pos.cashbox.transfers.pending_title') }}</h2>
                        <ul class="divide-y divide-amber-200">
                            @foreach ($pendingTransfers as $transfer)
                                @php
                                    $transferAmountText = LocalizedMoney::format($transfer->amountMinor());
                                @endphp
                                <li wire:key="transfer-{{ $transfer->id }}" class="flex flex-wrap items-center gap-3 px-4 py-3 text-sm">
                                    <div class="min-w-0 flex-1">
                                        <p class="font-medium text-gray-900">{{ $transfer->kind->label() }} · <span dir="ltr" class="tabular-nums">{{ $transferAmountText }}</span> TRY · <span dir="auto">{{ $transfer->reason }}</span></p>
                                        @if ($transfer->id === $instructedTransferId)
                                            <p class="mt-1 font-semibold text-amber-950" data-transfer-move>{{ __($transfer->kind === \App\Enums\CashboxTransferKind::DEPOSIT ? 'pos.cashbox.transfers.move_deposit' : 'pos.cashbox.transfers.move_withdrawal', ['amount' => $transferAmountText]) }}</p>
                                        @else
                                            <p class="mt-1 text-amber-950" data-transfer-verify>{{ __('pos.cashbox.transfers.verify', ['kind' => $transfer->kind->label(), 'amount' => $transferAmountText, 'time' => Sale::displayTime($transfer->started_at), 'name' => $transfer->starter?->name]) }}</p>
                                        @endif
                                    </div>
                                    <button type="button" wire:click="confirmTransfer({{ $transfer->id }})" wire:loading.attr="disabled" wire:target="confirmTransfer" class="{{ $secondaryButton }} bg-gray-900 text-white ring-gray-900 hover:bg-gray-800">{{ __('pos.cashbox.transfers.confirm') }}</button>
                                    <button type="button" wire:click="openTransferRelease({{ $transfer->id }})" class="{{ $secondaryButton }} text-red-700">{{ __('pos.cashbox.transfers.release') }}</button>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                <section aria-labelledby="cashbox-movements-title" class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    <h2 id="cashbox-movements-title" class="border-b border-gray-100 px-4 py-3 text-base font-semibold text-gray-900">{{ __('pos.cashbox.movements_title') }}</h2>
                    @if ($movements->isEmpty())
                        <p class="px-4 py-6 text-center text-sm text-gray-600">{{ __('pos.cashbox.no_movements') }}</p>
                    @else
                        <table class="w-full text-sm">
                            <thead class="text-xs text-gray-600">
                                <tr>
                                    <th scope="col" class="px-4 py-2 text-start font-medium">{{ __('pos.cashbox.columns.time') }}</th>
                                    <th scope="col" class="px-4 py-2 text-start font-medium">{{ __('pos.cashbox.columns.reference') }}</th>
                                    <th scope="col" class="px-4 py-2 text-start font-medium">{{ __('pos.cashbox.columns.employee') }}</th>
                                    <th scope="col" class="px-4 py-2 text-end font-medium">{{ __('pos.cashbox.columns.amount') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($movements as $movement)
                                    <tr wire:key="movement-{{ $movement->id }}">
                                        <td class="px-4 py-2"><time dir="ltr" datetime="{{ $movement->occurred_at->toIso8601String() }}" class="text-gray-700">{{ Sale::displayTime($movement->occurred_at) }}</time></td>
                                        <td class="px-4 py-2">@include('livewire.pos.partials.cash-movement-source')</td>
                                        <td class="px-4 py-2 text-gray-700">{{ $movement->recorder?->name }}</td>
                                        <td dir="ltr" @class(['px-4 py-2 text-end font-semibold tabular-nums', 'text-gray-900' => $movement->signedMinor() > 0, 'text-red-700' => $movement->signedMinor() < 0])>{{ $signed($movement->signedMinor()) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </section>
            </div>

            <section aria-labelledby="cashbox-close-title" class="space-y-4 lg:col-span-5">
                <form wire:submit="startTransfer" aria-labelledby="cashbox-transfer-title" class="space-y-4 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <h2 id="cashbox-transfer-title" class="text-base font-semibold text-gray-900">{{ __('pos.cashbox.transfers.title') }}</h2>
                    <p class="text-sm text-gray-600">{{ __('pos.cashbox.transfers.help') }}</p>
                    <fieldset>
                        <legend class="block text-sm font-medium text-gray-800">{{ __('pos.cashbox.transfers.fields.kind') }}</legend>
                        <div class="mt-1 grid grid-cols-2 gap-2">
                            @foreach ($transferKinds as $kind)
                                <label class="flex min-h-11 items-center gap-2 rounded-lg px-3 ring-1 ring-gray-300 has-[:checked]:bg-blue-50 has-[:checked]:ring-blue-500">
                                    <input type="radio" wire:model.live="transferKind" value="{{ $kind->value }}" class="text-blue-600 focus:ring-blue-500">
                                    <span class="text-sm text-gray-900">{{ $kind->label() }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    <x-operations.money-input id="cashbox-transfer-amount" wire:model="transferAmount" error="amount" :label="__('pos.cashbox.transfers.fields.amount')" />
                    @if ($transferKind === 'withdrawal' && $available !== null)
                        <p class="text-sm text-gray-700" data-transfer-available>{{ __('pos.cashbox.transfers.available', ['amount' => LocalizedMoney::format(max(0, $available))]) }}</p>
                    @endif
                    <div>
                        <label for="cashbox-transfer-reason" class="block text-sm font-medium text-gray-800">{{ __('pos.cashbox.transfers.fields.reason') }}</label>
                        <textarea id="cashbox-transfer-reason" dir="auto" rows="2" maxlength="{{ \App\Models\CashboxTransfer::MAX_REASON_LENGTH }}" wire:model="transferReason" required @error('reason') aria-invalid="true" @enderror class="{{ $inputClass }} mt-1"></textarea>
                        @error('reason') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <button type="submit" wire:loading.attr="disabled" wire:target="startTransfer" class="{{ $primaryButton }} bg-blue-600 hover:bg-blue-700">{{ __('pos.cashbox.transfers.submit') }}</button>
                </form>

                {{-- The difference preview is presentation only; closing checks the count on the server. --}}
                <form
                    wire:submit="close"
                    class="space-y-4 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200"
                    x-data="{
                        expected: @js($expectedShown),
                        get difference() {
                            const counted = $tryMoney.toMinor($wire.countedClosing);
                            return counted === null ? null : counted - this.expected;
                        },
                    }"
                >
                    <h2 id="cashbox-close-title" class="text-base font-semibold text-gray-900">{{ __('pos.cashbox.close_title') }}</h2>
                    <p class="text-sm text-gray-600">{{ __('pos.cashbox.close_help') }}</p>
                    <x-operations.money-input id="cashbox-counted-closing" wire:model="countedClosing" error="counted_closing" :label="__('pos.cashbox.counted_closing')" />
                    <p class="text-sm text-gray-700" x-show="difference !== null" x-cloak aria-live="polite">
                        {{ __('pos.cashbox.difference') }}:
                        <span dir="ltr" class="font-bold tabular-nums" :class="difference === 0 ? 'text-gray-900' : 'text-red-700'" x-text="difference === null ? '' : (difference > 0 ? '+' : '') + $tryMoney.format(difference)"></span> TRY
                    </p>
                    <div>
                        <label for="cashbox-closing-note" class="block text-sm font-medium text-gray-800">{{ __('pos.cashbox.closing_note') }}</label>
                        <p id="cashbox-closing-note-help" class="text-xs text-gray-600">{{ __('pos.cashbox.closing_note_help') }}</p>
                        <textarea id="cashbox-closing-note" dir="auto" rows="2" maxlength="{{ \App\Models\CashboxSession::MAX_NOTE_LENGTH }}" wire:model="closingNote" aria-describedby="cashbox-closing-note-help" class="{{ $inputClass }} mt-1"></textarea>
                        @error('closing_note') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <button type="submit" wire:loading.attr="disabled" wire:target="close" @disabled($pendingIntakes->isNotEmpty() || $pendingPayoutCount > 0 || $pendingTransfers->isNotEmpty()) class="{{ $primaryButton }} bg-gray-900 hover:bg-gray-800">{{ __('pos.cashbox.close') }}</button>
                </form>
            </section>
        @endif
    </div>

    <section aria-labelledby="cashbox-history-title" class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
        <h2 id="cashbox-history-title" class="border-b border-gray-100 px-4 py-3 text-base font-semibold text-gray-900">{{ __('pos.cashbox.history_title') }}</h2>
        @if ($history->isEmpty())
            <p class="px-4 py-6 text-center text-sm text-gray-600">{{ __('pos.cashbox.no_history') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs text-gray-600">
                        <tr>
                            <th scope="col" class="px-4 py-2 text-start font-medium">{{ __('pos.cashbox.columns.period') }}</th>
                            <th scope="col" class="px-4 py-2 text-end font-medium">{{ __('pos.cashbox.columns.opening') }}</th>
                            <th scope="col" class="px-4 py-2 text-end font-medium">{{ __('pos.cashbox.columns.expected') }}</th>
                            <th scope="col" class="px-4 py-2 text-end font-medium">{{ __('pos.cashbox.columns.counted') }}</th>
                            <th scope="col" class="px-4 py-2 text-end font-medium">{{ __('pos.cashbox.columns.difference') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($history as $closed)
                            @php($difference = \App\Services\Purchasing\Money::fromDecimal($closed->closing_difference))
                            <tr wire:key="closed-{{ $closed->id }}">
                                <td class="px-4 py-2">
                                    <a href="{{ route('pos.cashbox.session', [$store, $closed->id]) }}" class="block font-medium text-blue-700 underline focus-visible:outline-2 focus-visible:outline-blue-600" aria-label="{{ __('pos.cashbox.reconciliation.open_session', ['period' => Sale::displayTime($closed->opened_at).' – '.Sale::displayTime($closed->closed_at)]) }}"><span dir="ltr">{{ Sale::displayTime($closed->opened_at) }} – {{ Sale::displayTime($closed->closed_at) }}</span></a>
                                    <span class="block text-xs text-gray-600">{{ $closed->opener?->name }} · {{ $closed->closer?->name }}</span>
                                    @if ($closed->opening_note)
                                        <span class="block text-xs text-gray-700">{{ $closed->opening_note }}</span>
                                    @endif
                                    @if ($closed->closing_note)
                                        <span class="block text-xs text-gray-700">{{ $closed->closing_note }}</span>
                                    @endif
                                </td>
                                <td dir="ltr" class="px-4 py-2 text-end tabular-nums">{{ LocalizedMoney::format($closed->openingCountedMinor()) }}</td>
                                <td dir="ltr" class="px-4 py-2 text-end tabular-nums">{{ LocalizedMoney::format(\App\Services\Purchasing\Money::fromDecimal($closed->expected_closing)) }}</td>
                                <td dir="ltr" class="px-4 py-2 text-end tabular-nums">{{ LocalizedMoney::format((int) $closed->countedClosingMinor()) }}</td>
                                <td dir="ltr" @class(['px-4 py-2 text-end font-semibold tabular-nums', 'text-gray-900' => $difference === 0, 'text-red-700' => $difference !== 0])>{{ $signed($difference) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3">{{ $history->links() }}</div>
        @endif
    </section>

    <x-operations.dialog :id="CashboxPage::RELEASE_DIALOG" :title="__('pos.cashbox.release_title')" :direction="$textDirection">
        <form wire:submit="release" class="space-y-3">
            <p class="text-sm text-gray-700">{{ __('pos.cashbox.release_help') }}</p>
            @error('cashbox') <p class="text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
            <div>
                <label for="cashbox-release-note" class="block text-sm font-medium text-gray-800">{{ __('pos.cashbox.release_note') }}</label>
                <textarea id="cashbox-release-note" dir="auto" rows="2" maxlength="{{ \App\Models\CashIntake::MAX_NOTE_LENGTH }}" wire:model="releaseNote" class="{{ $inputClass }} mt-1"></textarea>
                @error('release_note') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
            </div>
            <button type="submit" wire:loading.attr="disabled" wire:target="release" class="{{ $primaryButton }} bg-red-700 hover:bg-red-800">{{ __('pos.cashbox.confirm_release') }}</button>
        </form>
    </x-operations.dialog>

    <x-operations.dialog :id="CashboxPage::TRANSFER_RELEASE_DIALOG" :title="__('pos.cashbox.transfers.release_title')" :direction="$textDirection">
        <form wire:submit="releaseTransfer" class="space-y-3">
            <p class="text-sm text-gray-700">{{ __('pos.cashbox.transfers.release_help') }}</p>
            <fieldset>
                <legend class="block text-sm font-medium text-gray-800">{{ __('pos.expenses.fields.resolution') }}</legend>
                <div class="mt-1 space-y-2">
                    @foreach ($resolutions as $resolution)
                        <label class="flex min-h-11 items-center gap-2 rounded-lg px-3 ring-1 ring-gray-300">
                            <input type="radio" wire:model="transferResolution" value="{{ $resolution->value }}" class="text-blue-600 focus:ring-blue-500">
                            <span class="text-sm text-gray-900">{{ __('pos.cashbox.transfers.resolutions.'.$resolution->value) }}</span>
                        </label>
                    @endforeach
                </div>
                @error('transferResolution') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
            </fieldset>
            <div>
                <label for="cashbox-transfer-release-note" class="block text-sm font-medium text-gray-800">{{ __('pos.cashbox.release_note') }}</label>
                <textarea id="cashbox-transfer-release-note" dir="auto" rows="2" maxlength="{{ \App\Models\CashPayout::MAX_NOTE_LENGTH }}" wire:model="transferReleaseNote" class="{{ $inputClass }} mt-1"></textarea>
                @error('transferReleaseNote') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
            </div>
            <button type="submit" wire:loading.attr="disabled" wire:target="releaseTransfer" class="{{ $primaryButton }} bg-red-700 hover:bg-red-800">{{ __('pos.cashbox.transfers.release_submit') }}</button>
        </form>
    </x-operations.dialog>
</section>
