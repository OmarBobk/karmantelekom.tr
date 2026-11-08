<?php

declare(strict_types=1);

namespace App\Livewire\Pos;

use App\Actions\Expenses\ConfirmCashPayout;
use App\Actions\Expenses\ReleaseCashPayout;
use App\Actions\Expenses\SubmitExpense;
use App\Actions\Inventory\FindUnitsByIdentifier;
use App\Enums\CashPayoutResolution;
use App\Enums\ExpenseCategory;
use App\Enums\ExpenseMethod;
use App\Exceptions\Inventory\IdempotencyConflictException;
use App\Exceptions\Purchasing\InvalidAmountException;
use App\Livewire\Pos\Concerns\UsesPosLocale;
use App\Models\CashboxSession;
use App\Models\CashPayout;
use App\Models\Expense;
use App\Models\ExpenseSubmission;
use App\Models\OperationalSupplier;
use App\Models\Sale;
use App\Models\Store;
use App\Services\Purchasing\LocalizedMoney;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * A branch's general expenses: record one, hand out cash for cash expenses, and see history.
 * Owner only.
 *
 * A cash expense first reserves its amount on the session the form was shown with; staff are
 * told to hand the cash out only after that succeeds. Each pending payout is confirmed or
 * released by naming the payout and session it was displayed with. The request key is kept
 * through failures and retries and renewed only after a confirmed submission. The hand-out
 * instruction follows only a newly created payout; a resent submission shows the payout's
 * current state instead, and pending payouts after a reload ask the operator to check first.
 */
class ExpensesPage extends Component
{
    use UsesPosLocale;
    use WithPagination;

    public const RELEASE_DIALOG = 'expense-payout-release';

    #[Locked]
    public int $storeId;

    #[Locked]
    public string $requestKey = '';

    /** The open session the form shows; a cash payout is reserved only on this session. */
    #[Locked]
    public ?int $sessionShown = null;

    /** The payout this page just created; only then is the owner told to hand the cash out. */
    #[Locked]
    public ?int $instructedPayoutId = null;

    /**
     * A submission this page resent and got back as already recorded. Its current state is shown
     * instead of a hand-out instruction, so the operator checks before handing out any cash.
     */
    #[Locked]
    public ?int $replayedSubmissionId = null;

    public string $category = '';

    public string $description = '';

    public string $amount = '';

    public string $expenseDate = '';

    public string $method = 'cash';

    public string $reference = '';

    public string $supplierId = '';

    public string $deviceCode = '';

    /** The device chosen from this branch's lookup results; only a listed match can be chosen. */
    #[Locked]
    public ?int $unitId = null;

    /**
     * @var list<array{unit_id: int, label: string}>
     */
    #[Locked]
    public array $deviceMatches = [];

    #[Locked]
    public bool $deviceSearched = false;

    #[Locked]
    public ?int $releasePayoutId = null;

    public string $releaseResolution = '';

    public string $releaseNote = '';

    public function mount(Store $store): void
    {
        $this->storeId = $store->id;
        $this->requestKey = (string) Str::uuid();
        $this->expenseDate = self::today();
        $this->snapshotSession($this->authorizeExpenses());
    }

    public function refreshSession(): void
    {
        $this->snapshotSession($this->authorizeExpenses());
        $this->resetValidation();
    }

    public function record(SubmitExpense $submit): void
    {
        $store = $this->authorizeExpenses();

        try {
            $amount = LocalizedMoney::toCanonical($this->amount);
        } catch (InvalidAmountException) {
            throw ValidationException::withMessages(['amount' => __('pos.errors.amount_format', ['example' => LocalizedMoney::EXAMPLE])]);
        }

        $input = [
            'method' => $this->method,
            'category' => $this->category,
            'description' => $this->description,
            'amount' => $amount,
            'expense_date' => $this->expenseDate,
            'reference' => $this->reference,
            'operational_supplier_id' => $this->supplierId === '' ? null : $this->supplierId,
            'inventory_unit_id' => $this->unitId,
        ];

        try {
            $submission = $submit(auth()->user(), $store, $this->sessionShown, $input, $this->requestKey);
        } catch (IdempotencyConflictException) {
            throw ValidationException::withMessages(['amount' => __('pos.expenses.errors.request_conflict')]);
        } catch (ValidationException $exception) {
            if (array_key_exists('cashbox', $exception->errors()) || array_key_exists('amount', $exception->errors())) {
                $this->snapshotSession($store);
            }

            throw $exception;
        }

        $this->resetForm();
        $this->resetPage();

        if (! $submission->wasRecentlyCreated) {
            $this->instructedPayoutId = null;
            $this->replayedSubmissionId = $submission->id;
            $this->notify('info', __('pos.expenses.replay.notice'));

            return;
        }

        $this->replayedSubmissionId = null;
        $payout = $submission->payout;

        if ($payout !== null) {
            $this->instructedPayoutId = $payout->id;
            $this->notify('info', __('pos.expenses.hand_out_title', ['amount' => LocalizedMoney::format($payout->amountMinor())]));

            return;
        }

        $this->notify('success', __('pos.expenses.recorded', ['amount' => LocalizedMoney::format($submission->expense->amountMinor())]));
    }

    /**
     * Exact IMEI or serial lookup within this branch, through the shared device lookup.
     */
    public function findDevice(FindUnitsByIdentifier $find): void
    {
        $store = $this->authorizeExpenses();
        $this->authorize('viewInventory', $store);

        $this->resetValidation('inventory_unit_id');
        $this->unitId = null;
        $this->deviceMatches = collect($find(auth()->user(), $this->deviceCode, $store)['current'])
            ->map(fn (array $match): array => [
                'unit_id' => $match['unit_id'],
                'label' => trim($match['product_name'].' · '.$match['matched_value']),
            ])
            ->values()
            ->all();
        $this->deviceSearched = true;

        if (count($this->deviceMatches) === 1) {
            $this->unitId = $this->deviceMatches[0]['unit_id'];
        }
    }

    public function chooseDevice(int $unitId): void
    {
        $this->authorizeExpenses();

        if (in_array($unitId, array_column($this->deviceMatches, 'unit_id'), true)) {
            $this->unitId = $unitId;
        }
    }

    public function clearDevice(): void
    {
        $this->reset('unitId', 'deviceMatches', 'deviceSearched', 'deviceCode');
        $this->resetValidation('inventory_unit_id');
    }

    /**
     * Confirm the displayed payout's cash was handed out, naming the session it was shown with.
     */
    public function confirm(ConfirmCashPayout $confirm, int $payoutId, int $sessionId): void
    {
        $store = $this->authorizeExpenses();

        try {
            $expense = $confirm(auth()->user(), $store, $payoutId, $sessionId);
        } finally {
            $this->snapshotSession($store);
        }

        if ($this->instructedPayoutId === $payoutId) {
            $this->instructedPayoutId = null;
        }

        $this->resetValidation();
        $this->resetPage();
        $this->notify('success', __('pos.expenses.paid_out', ['amount' => LocalizedMoney::format($expense->amountMinor())]));
    }

    public function openRelease(int $payoutId): void
    {
        $store = $this->authorizeExpenses();
        $this->authorize('manageCashbox', $store);
        $payout = CashPayout::query()->where('store_id', $store->id)->pending()->find($payoutId);

        if ($payout === null) {
            $this->notify('error', __('pos.expenses.errors.payout_not_pending'));

            return;
        }

        $this->resetValidation();
        $this->releasePayoutId = $payout->id;
        $this->releaseResolution = '';
        $this->releaseNote = '';
        $this->dispatch('open-dialog', id: self::RELEASE_DIALOG);
    }

    public function release(ReleaseCashPayout $release): void
    {
        $store = $this->authorizeExpenses();

        if ($this->releasePayoutId === null) {
            return;
        }

        $release(auth()->user(), $store, $this->releasePayoutId, $this->releaseResolution ?: null, $this->releaseNote);

        if ($this->instructedPayoutId === $this->releasePayoutId) {
            $this->instructedPayoutId = null;
        }

        $this->releasePayoutId = null;
        $this->reset('releaseResolution', 'releaseNote');
        $this->snapshotSession($store);
        $this->dispatch('close-dialog', id: self::RELEASE_DIALOG);
        $this->notify('success', __('pos.expenses.released'));
    }

    public function render(): View
    {
        $store = $this->authorizeExpenses();
        $session = CashboxSession::openFor($store);

        return view('livewire.pos.expenses-page', [
            'store' => $store,
            'session' => $session,
            'sessionMatches' => $session?->id === $this->sessionShown,
            'available' => $session?->availableForPayoutMinor(),
            'replayed' => $this->replayedSubmissionId === null ? null : ExpenseSubmission::query()
                ->where('store_id', $store->id)
                ->with(['payout.releaser:id,name', 'payout.expense', 'expense'])
                ->find($this->replayedSubmissionId),
            'pendingPayouts' => CashPayout::query()
                ->where('store_id', $store->id)
                ->pending()
                ->with(['starter:id,name', 'supplier:id,name', 'unit:id,product_definition_id', 'unit.productDefinition:id,name', 'unit.identifiers'])
                ->oldest('started_at')
                ->oldest('id')
                ->get(),
            'expenses' => Expense::query()
                ->where('store_id', $store->id)
                ->with(['recorder:id,name', 'supplier:id,name', 'unit:id,product_definition_id', 'unit.productDefinition:id,name', 'unit.identifiers'])
                ->latest('expense_date')
                ->latest('id')
                ->paginate(25),
            'categories' => ExpenseCategory::cases(),
            'methods' => ExpenseMethod::cases(),
            'resolutions' => CashPayoutResolution::cases(),
            'canRelease' => auth()->user()->can('manageCashbox', $store),
            'suppliers' => auth()->user()->can('viewAny', OperationalSupplier::class)
                ? OperationalSupplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])
                : collect(),
            'canLinkDevice' => auth()->user()->can('viewInventory', $store),
            'chosenDevice' => collect($this->deviceMatches)->firstWhere('unit_id', $this->unitId),
            'today' => self::today(),
            ...$this->localeViewData(),
        ])->layout('layouts.pos', ['posStore' => $store])->title(__('pos.expenses.title', ['store' => $store->code]));
    }

    private function snapshotSession(Store $store): void
    {
        $this->sessionShown = CashboxSession::openFor($store)?->id;
    }

    private function resetForm(): void
    {
        $this->reset('category', 'description', 'amount', 'reference', 'supplierId', 'deviceCode', 'unitId', 'deviceMatches', 'deviceSearched');
        $this->method = ExpenseMethod::CASH->value;
        $this->expenseDate = self::today();
        $this->requestKey = (string) Str::uuid();
        $this->resetValidation();
    }

    private static function today(): string
    {
        return Carbon::now(Sale::DISPLAY_TIMEZONE)->toDateString();
    }

    private function authorizeExpenses(): Store
    {
        $store = Store::query()->findOrFail($this->storeId);
        $this->authorize('manageExpenses', $store);

        return $store;
    }

    private function notify(string $type, string $message): void
    {
        $this->dispatch('notify', ['type' => $type, 'message' => $message]);
    }
}
