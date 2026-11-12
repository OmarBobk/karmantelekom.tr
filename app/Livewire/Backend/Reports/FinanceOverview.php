<?php

declare(strict_types=1);

namespace App\Livewire\Backend\Reports;

use App\Enums\CashboxTransferKind;
use App\Enums\ExpenseCategory;
use App\Livewire\Backend\Concerns\UsesOperationalLocale;
use App\Models\CashboxSession;
use App\Models\Expense;
use App\Models\OperationalSupplier;
use App\Models\Store;
use App\Models\User;
use App\Services\Customers\CustomerAccount;
use App\Services\Purchasing\Money;
use App\Services\Purchasing\SupplierAccount;
use App\Services\Reporting\LocalDateRange;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Owner finance overview: period expenses and closed-session discrepancies for an Istanbul date
 * range, and current cashbox, supplier, and customer balances. Each section is shown only for
 * the branches where the user holds that section's permission; nothing for the other sections
 * is queried or passed to the view.
 */
class FinanceOverview extends Component
{
    use UsesOperationalLocale;
    use WithPagination;

    public const PER_PAGE = 25;

    private const PENDING_COLUMNS = 'count(*) as pending_count, coalesce(sum(amount), 0) as pending_amount';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    public function mount(): void
    {
        $this->authorize('viewFinanceOverview', Store::class);

        $this->from = Str::trim($this->from);
        $this->to = Str::trim($this->to);

        if ($this->from === '' && $this->to === '') {
            $this->showToday();
        }
    }

    public function applyRange(): void
    {
        $this->from = Str::trim($this->from);
        $this->to = Str::trim($this->to);
        $this->resetPage('sessions');
    }

    public function showToday(): void
    {
        $this->from = $this->to = LocalDateRange::today();
        $this->resetPage('sessions');
    }

    #[Layout('layouts.backend')]
    public function render(): View
    {
        $this->authorize('viewFinanceOverview', Store::class);

        $user = auth()->user();
        $stores = Store::query()->accessibleTo($user)->orderBy('code')->get();
        $rangeError = LocalDateRange::error($this->from, $this->to);

        return view('livewire.backend.reports.finance-overview', [
            'rangeError' => $rangeError,
            'expenses' => $this->expenses($this->storesWhere($stores, $user, 'manageExpenses'), $rangeError),
            'cashbox' => $this->cashbox($this->storesWhere($stores, $user, 'manageCashbox'), $rangeError),
            'suppliers' => $user->can('viewAccounts', OperationalSupplier::class) ? $this->suppliers($stores) : null,
            'debts' => $this->debts($this->storesWhere($stores, $user, 'receiveCustomerPayments')),
            'canViewDashboard' => $user->can('viewOperationsDashboard', Store::class),
            ...$this->localeViewData(),
        ])->title(__('reports.finance.title'));
    }

    /**
     * @param  Collection<int, Store>  $stores
     * @return Collection<int, Store>
     */
    private function storesWhere(Collection $stores, User $user, string $ability): Collection
    {
        return $stores->filter(fn (Store $store): bool => $user->can($ability, $store))->values();
    }

    /**
     * Confirmed expenses by branch and category for the business dates.
     *
     * @param  Collection<int, Store>  $stores
     * @return array{rows: list<array{store: Store, categories: array<string, int>, total: int}>, categories: list<ExpenseCategory>, category_totals: array<string, int>, total: int}|null
     */
    private function expenses(Collection $stores, ?string $rangeError): ?array
    {
        if ($stores->isEmpty()) {
            return null;
        }

        $totals = $rangeError === null ? Expense::totalsByStoreAndCategory($stores->modelKeys(), $this->from, $this->to) : [];
        $rows = $stores->map(fn (Store $store): array => [
            'store' => $store,
            'categories' => $totals[$store->id] ?? [],
            'total' => Money::sumUncapped($totals[$store->id] ?? []),
        ])->all();

        $categories = [];
        $categoryTotals = [];

        foreach (ExpenseCategory::cases() as $category) {
            $amounts = array_column(array_column($rows, 'categories'), $category->value);

            if ($amounts !== []) {
                $categories[] = $category;
                $categoryTotals[$category->value] = Money::sumUncapped($amounts);
            }
        }

        return [
            'rows' => $rows,
            'categories' => $categories,
            'category_totals' => $categoryTotals,
            'total' => Money::sumUncapped(array_column($rows, 'total')),
        ];
    }

    /**
     * Current cashbox per branch, with the Cashbox page's figures, and stored closing
     * differences of sessions closed in the period.
     *
     * @param  Collection<int, Store>  $stores
     * @return array{live: list<array<string, mixed>>, differences: array<string, mixed>, sessions: LengthAwarePaginator}|null
     */
    private function cashbox(Collection $stores, ?string $rangeError): ?array
    {
        if ($stores->isEmpty()) {
            return null;
        }

        $live = $stores->map(function (Store $store): array {
            $session = CashboxSession::openFor($store);

            if ($session === null) {
                return [
                    'store' => $store,
                    'state' => CashboxSession::trackingStarted($store) ? 'closed' : 'untracked',
                    'previous' => CashboxSession::query()->where('store_id', $store->id)->closed()->latest('closed_at')->latest('id')->first(),
                ];
            }

            $transfers = $session->transfers()->pending()->toBase()->selectRaw('kind, '.self::PENDING_COLUMNS)->groupBy('kind')->get()->keyBy('kind');

            return [
                'store' => $store,
                'state' => 'open',
                'session' => $session,
                'expected' => $session->expectedMinor(),
                'available' => $session->availableForPayoutMinor(),
                'pending' => [
                    'expense_payouts' => self::pendingTotals($session->payouts()->pending()->toBase()->selectRaw(self::PENDING_COLUMNS)->first()),
                    'supplier_payouts' => self::pendingTotals($session->supplierPayouts()->pending()->toBase()->selectRaw(self::PENDING_COLUMNS)->first()),
                    'withdrawals' => self::pendingTotals($transfers->get(CashboxTransferKind::WITHDRAWAL->value)),
                    'deposits' => self::pendingTotals($transfers->get(CashboxTransferKind::DEPOSIT->value)),
                    'intakes' => self::pendingTotals($session->intakes()->pending()->toBase()->selectRaw(self::PENDING_COLUMNS)->first()),
                ],
            ];
        })->all();

        if ($rangeError !== null) {
            return ['live' => $live, 'differences' => null, 'sessions' => new LengthAwarePaginator([], 0, self::PER_PAGE)];
        }

        $from = LocalDateRange::date($this->from);
        $to = LocalDateRange::date($this->to);
        $byStore = CashboxSession::closingDifferencesByStore($stores->modelKeys(), $from, $to);
        $rows = $stores->map(fn (Store $store): array => [
            'store' => $store,
            ...($byStore[$store->id] ?? ['sessions' => 0, 'with_difference' => 0, 'overage' => 0, 'shortage' => 0]),
        ])->all();
        $overage = Money::sumUncapped(array_column($rows, 'overage'));
        $shortage = Money::sumUncapped(array_column($rows, 'shortage'));

        return [
            'live' => $live,
            'differences' => [
                'rows' => $rows,
                'sessions' => array_sum(array_column($rows, 'sessions')),
                'with_difference' => array_sum(array_column($rows, 'with_difference')),
                'overage' => $overage,
                'shortage' => $shortage,
                'net' => $overage - $shortage,
            ],
            'sessions' => CashboxSession::query()
                ->whereIn('store_id', $stores->modelKeys())
                ->closedOnLocalDays($from, $to)
                ->with('store:id,code,name')
                ->latest('closed_at')
                ->latest('id')
                ->paginate(self::PER_PAGE, ['*'], 'sessions'),
        ];
    }

    /**
     * Current supplier balances from the ledger, per currency, in every accessible branch.
     *
     * @param  Collection<int, Store>  $stores
     * @return array{totals: array<string, array<string, mixed>>, suppliers: LengthAwarePaginator, pending: array<int, array{count: int, amount: int}>, stores: Collection<int, Store>}
     */
    private function suppliers(Collection $stores): array
    {
        $accounts = app(SupplierAccount::class);
        $storeIds = $stores->modelKeys();

        return [
            'totals' => $accounts->allBalances($storeIds),
            'suppliers' => $accounts->supplierBalances($storeIds, self::PER_PAGE, 'suppliers')->through(fn (object $row): array => [
                'supplier_id' => (int) $row->operational_supplier_id,
                'name' => $row->name,
                'currency' => $row->currency,
                'balance' => Money::fromDecimal((string) $row->balance),
                'open_invoices' => Money::fromDecimal((string) $row->open_invoices),
            ]),
            'pending' => $accounts->pendingCashPayouts($storeIds),
            'stores' => $stores->keyBy('id'),
        ];
    }

    /**
     * Current customer debt per branch and cash payments waiting at the cashbox.
     *
     * @param  Collection<int, Store>  $stores
     * @return array{rows: list<array{store: Store, due: int, debtors: int, pending: array{count: int, amount: int}}>, due: int, debtors: int}|null
     */
    private function debts(Collection $stores): ?array
    {
        if ($stores->isEmpty()) {
            return null;
        }

        $accounts = app(CustomerAccount::class);
        $balances = $accounts->balancesByStore($stores->modelKeys());
        $pending = $accounts->pendingCashPayments($stores->modelKeys());
        $rows = $stores->map(fn (Store $store): array => [
            'store' => $store,
            'due' => $balances[$store->id]['due'] ?? 0,
            'debtors' => $balances[$store->id]['debtors'] ?? 0,
            'pending' => $pending[$store->id] ?? ['count' => 0, 'amount' => 0],
        ])->all();

        return [
            'rows' => $rows,
            'due' => Money::sumUncapped(array_column($rows, 'due')),
            'debtors' => array_sum(array_column($rows, 'debtors')),
        ];
    }

    /**
     * @return array{count: int, amount: int}
     */
    private static function pendingTotals(?object $row): array
    {
        return $row === null ? ['count' => 0, 'amount' => 0] : ['count' => (int) $row->pending_count, 'amount' => Money::fromDecimal((string) $row->pending_amount)];
    }
}
