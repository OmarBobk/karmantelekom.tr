@php
    use App\Livewire\Pos\ExpensesPage;
    use App\Models\CashPayout;
    use App\Models\Expense;
    use App\Models\Sale;
    use App\Services\Purchasing\LocalizedMoney;
    $inputClass = 'block min-h-11 w-full rounded-lg border-gray-300 text-base shadow-sm focus:border-blue-500 focus:ring-blue-500';
    $primaryButton = 'inline-flex min-h-12 w-full items-center justify-center rounded-lg px-5 text-base font-semibold text-white shadow-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 disabled:cursor-not-allowed disabled:opacity-60';
    $secondaryButton = 'inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-white px-4 text-sm font-semibold text-gray-800 shadow-sm ring-1 ring-gray-300 hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 disabled:cursor-not-allowed disabled:opacity-60';
@endphp
<section dir="{{ $textDirection }}" lang="{{ $locale }}" aria-labelledby="expenses-title" class="space-y-4 text-start">
    <h1 id="expenses-title" class="text-xl font-semibold text-gray-900">{{ __('pos.expenses.heading') }}</h1>

    <div class="grid gap-4 lg:grid-cols-12">
        <div class="space-y-4 lg:col-span-7">
            @if ($pendingPayouts->isNotEmpty())
                <section aria-labelledby="expenses-pending-title" class="rounded-xl bg-white shadow-sm ring-1 ring-amber-300">
                    <h2 id="expenses-pending-title" class="border-b border-amber-100 px-4 py-3 text-base font-semibold text-gray-900">{{ __('pos.expenses.pending_title') }}</h2>
                    <p class="px-4 pt-2 text-xs text-gray-600">{{ __('pos.expenses.pending_help') }}</p>
                    @error('cashbox') <p class="mx-4 mt-2 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800 ring-1 ring-red-200" role="alert">{{ $message }}</p> @enderror
                    <ul class="divide-y divide-gray-100">
                        @foreach ($pendingPayouts as $payout)
                            @php
                                $instructed = $payout->id === $instructedPayoutId;
                            @endphp
                            <li wire:key="payout-{{ $payout->id }}" @class(['space-y-2 px-4 py-3 text-sm', 'bg-sky-50 ring-2 ring-inset ring-sky-500' => $instructed])>
                                @if ($instructed)
                                    <p class="text-base font-semibold text-sky-950" role="status" aria-live="polite">{{ __('pos.expenses.hand_out_title', ['amount' => LocalizedMoney::format($payout->amountMinor())]) }}</p>
                                    <p class="text-sky-950">{{ __('pos.expenses.hand_out_detail') }}</p>
                                @else
                                    <p class="text-xs font-medium text-amber-900" data-payout-verify>{{ __('pos.expenses.pending_verify') }}</p>
                                @endif
                                <div class="flex flex-wrap items-center gap-3">
                                    <div class="min-w-0 flex-1">
                                        <p class="font-medium text-gray-900">{{ $payout->category->label() }} · <span dir="auto">{{ $payout->description }}</span></p>
                                        <p class="text-xs text-gray-600">
                                            {{ $payout->starter?->name }} ·
                                            <time dir="ltr" datetime="{{ $payout->started_at->toIso8601String() }}">{{ Sale::displayTime($payout->started_at) }}</time>
                                            @if ($payout->reference)
                                                · <bdi dir="ltr" class="font-mono">{{ $payout->reference }}</bdi>
                                            @endif
                                        </p>
                                    </div>
                                    <span dir="ltr" class="font-semibold tabular-nums text-gray-900">{{ LocalizedMoney::format($payout->amountMinor()) }} TRY</span>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" wire:click="confirm({{ $payout->id }}, {{ $payout->cashbox_session_id }})" wire:loading.attr="disabled" wire:target="confirm" class="inline-flex min-h-11 flex-1 items-center justify-center rounded-lg bg-emerald-600 px-4 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 disabled:cursor-not-allowed disabled:opacity-60">
                                        {{ __('pos.expenses.confirm_paid_out', ['amount' => LocalizedMoney::format($payout->amountMinor())]) }}
                                    </button>
                                    @if ($canRelease)
                                        <button type="button" wire:click="openRelease({{ $payout->id }})" class="{{ $secondaryButton }} text-red-700">{{ __('pos.expenses.release') }}</button>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <section aria-labelledby="expenses-history-title" class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                <h2 id="expenses-history-title" class="border-b border-gray-100 px-4 py-3 text-base font-semibold text-gray-900">{{ __('pos.expenses.history_title') }}</h2>
                @if ($expenses->isEmpty())
                    <p class="px-4 py-6 text-center text-sm text-gray-600">{{ __('pos.expenses.no_history') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="text-xs text-gray-600">
                                <tr>
                                    <th scope="col" class="px-4 py-2 text-start font-medium">{{ __('pos.expenses.fields.date') }}</th>
                                    <th scope="col" class="px-4 py-2 text-start font-medium">{{ __('pos.expenses.fields.description') }}</th>
                                    <th scope="col" class="px-4 py-2 text-start font-medium">{{ __('pos.expenses.fields.method') }}</th>
                                    <th scope="col" class="px-4 py-2 text-end font-medium">{{ __('pos.expenses.fields.amount') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($expenses as $expense)
                                    <tr wire:key="expense-{{ $expense->id }}">
                                        <td class="px-4 py-2 align-top"><time dir="ltr" datetime="{{ $expense->expense_date->toDateString() }}" class="text-gray-900">{{ $expense->expense_date->toDateString() }}</time></td>
                                        <td class="px-4 py-2 align-top">
                                            <span class="block text-xs text-gray-600">{{ $expense->category->label() }}</span>
                                            <span dir="auto" class="block text-gray-900">{{ $expense->description }}</span>
                                            <span class="block text-xs text-gray-600">
                                                {{ $expense->recorder?->name }}
                                                @if ($expense->reference)
                                                    · <bdi dir="ltr" class="font-mono">{{ $expense->reference }}</bdi>
                                                @endif
                                            </span>
                                        </td>
                                        <td class="px-4 py-2 align-top text-gray-700">{{ $expense->method->label() }}</td>
                                        <td dir="ltr" class="px-4 py-2 text-end align-top font-semibold tabular-nums text-gray-900">{{ LocalizedMoney::format($expense->amountMinor()) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="px-4 py-3">{{ $expenses->links() }}</div>
                @endif
            </section>
        </div>

        <section aria-labelledby="expenses-record-title" class="space-y-4 lg:col-span-5">
            @if ($replayed)
                @php
                    $replayedPayout = $replayed->payout;
                    $replayState = match (true) {
                        $replayedPayout === null => 'recorded',
                        $replayedPayout->status === \App\Enums\CashPayoutStatus::COMPLETED => 'completed',
                        $replayedPayout->status === \App\Enums\CashPayoutStatus::RELEASED => 'released',
                        default => 'pending',
                    };
                    $replayAmount = LocalizedMoney::format(($replayedPayout ?? $replayed->expense)->amountMinor());
                @endphp
                <div wire:key="expense-replay-{{ $replayed->id }}-{{ $replayState }}" data-replay-state="{{ $replayState }}" @class([
                    'rounded-xl p-4 text-sm ring-2',
                    'bg-amber-50 text-amber-950 ring-amber-400' => $replayState === 'pending',
                    'bg-gray-50 text-gray-900 ring-gray-300' => $replayState !== 'pending',
                ]) role="status" aria-live="polite">
                    <p class="text-base font-semibold">{{ __('pos.expenses.replay.title') }}</p>
                    <p class="mt-1">
                        @if ($replayState === 'pending')
                            {{ __('pos.expenses.replay.pending', ['amount' => $replayAmount, 'time' => Sale::displayTime($replayedPayout->started_at)]) }}
                        @elseif ($replayState === 'completed')
                            {{ __('pos.expenses.replay.completed', ['amount' => $replayAmount, 'time' => Sale::displayTime($replayedPayout->completed_at)]) }}
                        @elseif ($replayState === 'released')
                            {{ __('pos.expenses.replay.released', ['amount' => $replayAmount, 'time' => Sale::displayTime($replayedPayout->released_at), 'resolution' => $replayedPayout->resolution?->label(), 'name' => $replayedPayout->releaser?->name]) }}
                        @else
                            {{ __('pos.expenses.replay.recorded', ['amount' => $replayAmount, 'method' => $replayed->expense->method->label(), 'time' => Sale::displayTime($replayed->expense->recorded_at)]) }}
                        @endif
                    </p>
                </div>
            @endif
            <form wire:submit="record" class="space-y-4 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200" x-data="{ method: $wire.entangle('method') }">
                <h2 id="expenses-record-title" class="text-base font-semibold text-gray-900">{{ __('pos.expenses.record_title') }}</h2>

                <fieldset>
                    <legend class="block text-sm font-medium text-gray-800">{{ __('pos.expenses.fields.method') }}</legend>
                    <div class="mt-1 grid gap-2 sm:grid-cols-3">
                        @foreach ($methods as $option)
                            <label wire:key="expense-method-{{ $option->value }}" class="flex min-h-11 items-center gap-2 rounded-lg px-3 ring-1 ring-gray-200">
                                <input type="radio" name="expense-method" value="{{ $option->value }}" x-model="method" class="text-blue-600 focus:ring-blue-500">
                                <span class="text-sm text-gray-900">{{ $option->label() }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('method') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                </fieldset>

                <div x-show="method === 'cash'" class="space-y-2">
                    @if ($session === null)
                        <p class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-950 ring-1 ring-amber-300" role="status">{{ __('pos.expenses.no_session') }}</p>
                    @elseif (! $sessionMatches)
                        <div class="flex flex-wrap items-center gap-2 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-950 ring-1 ring-amber-300" role="status">
                            <span class="flex-1">{{ __('pos.expenses.session_changed') }}</span>
                            <button type="button" wire:click="refreshSession" wire:loading.attr="disabled" wire:target="refreshSession" class="{{ $secondaryButton }}">{{ __('pos.cashbox.refresh') }}</button>
                        </div>
                    @else
                        <p class="text-sm text-gray-700">{{ __('pos.expenses.available') }}: <span dir="ltr" class="font-semibold tabular-nums text-gray-900">{{ LocalizedMoney::format(max(0, (int) $available)) }} TRY</span></p>
                    @endif
                    <p class="text-xs text-gray-600">{{ __('pos.expenses.cash_help') }}</p>
                    @if ($pendingPayouts->isEmpty())
                        @error('cashbox') <p class="text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                    @endif
                </div>
                <p x-show="method !== 'cash'" x-cloak class="text-xs text-gray-600">{{ __('pos.expenses.non_cash_help') }}</p>

                <div>
                    <label for="expense-category" class="block text-sm font-medium text-gray-800">{{ __('pos.expenses.fields.category') }} <span class="text-red-700" aria-hidden="true">*</span></label>
                    <select id="expense-category" wire:model="category" required @error('category') aria-invalid="true" aria-describedby="expense-category-error" @enderror class="{{ $inputClass }} mt-1">
                        <option value="">{{ __('pos.expenses.choose_category') }}</option>
                        @foreach ($categories as $option)
                            <option value="{{ $option->value }}">{{ $option->label() }}</option>
                        @endforeach
                    </select>
                    @error('category') <p id="expense-category-error" class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="expense-description" class="block text-sm font-medium text-gray-800">{{ __('pos.expenses.fields.description') }} <span class="text-red-700" aria-hidden="true">*</span></label>
                    <textarea id="expense-description" dir="auto" rows="2" required maxlength="{{ Expense::MAX_DESCRIPTION_LENGTH }}" wire:model="description" @error('description') aria-invalid="true" aria-describedby="expense-description-error" @enderror class="{{ $inputClass }} mt-1"></textarea>
                    @error('description') <p id="expense-description-error" class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                </div>

                <x-operations.money-input id="expense-amount" wire:model="amount" error="amount" :label="__('pos.expenses.fields.amount')" />

                <div>
                    <label for="expense-date" class="block text-sm font-medium text-gray-800">{{ __('pos.expenses.fields.date') }} <span class="text-red-700" aria-hidden="true">*</span></label>
                    <input id="expense-date" type="date" dir="ltr" required max="{{ $today }}" wire:model="expenseDate" @error('expense_date') aria-invalid="true" aria-describedby="expense-date-error" @enderror class="{{ $inputClass }} mt-1">
                    @error('expense_date') <p id="expense-date-error" class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="expense-reference" class="block text-sm font-medium text-gray-800">{{ __('pos.expenses.fields.reference') }}</label>
                    <p id="expense-reference-help" class="text-xs text-gray-600">{{ __('pos.expenses.reference_help') }}</p>
                    <input id="expense-reference" type="text" dir="ltr" autocomplete="off" maxlength="{{ Expense::MAX_REFERENCE_LENGTH }}" wire:model="reference" aria-describedby="expense-reference-help" class="{{ $inputClass }} mt-1 font-mono">
                    @error('reference') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                </div>

                <button type="submit" wire:loading.attr="disabled" wire:target="record" class="{{ $primaryButton }} bg-blue-600 hover:bg-blue-700">
                    <span wire:loading.remove wire:target="record">
                        <span x-show="method === 'cash'">{{ __('pos.expenses.reserve_cash') }}</span>
                        <span x-show="method !== 'cash'" x-cloak>{{ __('pos.expenses.record') }}</span>
                    </span>
                    <span wire:loading wire:target="record">{{ __('pos.expenses.recording') }}</span>
                </button>
            </form>
        </section>
    </div>

    <x-operations.dialog :id="ExpensesPage::RELEASE_DIALOG" :title="__('pos.expenses.release_title')" :direction="$textDirection">
        <form wire:submit="release" class="space-y-3">
            <p class="text-sm text-gray-700">{{ __('pos.expenses.release_help') }}</p>
            @error('cashbox') <p class="text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
            <fieldset>
                <legend class="block text-sm font-medium text-gray-800">{{ __('pos.expenses.fields.resolution') }}</legend>
                <div class="mt-1 space-y-2">
                    @foreach ($resolutions as $option)
                        <label wire:key="resolution-{{ $option->value }}" class="flex min-h-11 items-center gap-2 rounded-lg px-3 ring-1 ring-gray-200">
                            <input type="radio" name="expense-release-resolution" value="{{ $option->value }}" wire:model="releaseResolution" class="text-blue-600 focus:ring-blue-500">
                            <span class="text-sm text-gray-900">{{ $option->label() }}</span>
                        </label>
                    @endforeach
                </div>
                @error('resolution') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
            </fieldset>
            <div>
                <label for="expense-release-note" class="block text-sm font-medium text-gray-800">{{ __('pos.cashbox.release_note') }}</label>
                <textarea id="expense-release-note" dir="auto" rows="2" maxlength="{{ CashPayout::MAX_NOTE_LENGTH }}" wire:model="releaseNote" class="{{ $inputClass }} mt-1"></textarea>
                @error('release_note') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
            </div>
            <button type="submit" wire:loading.attr="disabled" wire:target="release" class="{{ $primaryButton }} bg-red-700 hover:bg-red-800">{{ __('pos.expenses.confirm_release') }}</button>
        </form>
    </x-operations.dialog>
</section>
