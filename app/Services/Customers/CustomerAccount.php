<?php

declare(strict_types=1);

namespace App\Services\Customers;

use App\Enums\CashIntakeSource;
use App\Models\CashIntake;
use App\Models\Customer;
use App\Models\CustomerDebt;
use App\Models\CustomerPaymentAllocation;
use App\Models\Store;
use App\Services\Purchasing\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Derived balances of store customers' debts: each debt's original amount less its allocations.
 * Nothing here writes or caches a balance.
 */
class CustomerAccount
{
    /**
     * The customer's debts in their store that still have a balance, oldest sale first (sale
     * completion time, then sale id), each with allocations_sum_amount loaded.
     *
     * @return Collection<int, CustomerDebt>
     */
    public function openDebts(Customer $customer): Collection
    {
        return CustomerDebt::query()
            ->select('customer_debts.*')
            ->join('sales', 'sales.id', '=', 'customer_debts.sale_id')
            ->where('customer_debts.customer_id', $customer->id)
            ->where('customer_debts.store_id', $customer->store_id)
            ->withSum('allocations', 'amount')
            ->with('sale:id,number,completed_at,total')
            ->orderBy('sales.completed_at')
            ->orderBy('sales.id')
            ->get()
            ->filter(fn (CustomerDebt $debt): bool => $debt->outstandingMinor() > 0)
            ->values();
    }

    /**
     * @param  Collection<int, CustomerDebt>  $openDebts
     */
    public static function totalDueMinor(Collection $openDebts): int
    {
        return $openDebts->sum(fn (CustomerDebt $debt): int => $debt->outstandingMinor());
    }

    /**
     * Customers of the store with a balance due, with the balance as due_amount (decimal).
     *
     * @return Builder<Customer>
     */
    public function debtors(Store $store): Builder
    {
        return $this->accounts($store)->having('due_amount', '>', 0);
    }

    /**
     * Customers of the store who had debts there and have paid them all, with due_amount 0.
     *
     * @return Builder<Customer>
     */
    public function settled(Store $store): Builder
    {
        return $this->accounts($store)->having('due_amount', '=', 0);
    }

    /**
     * Outstanding customer debt and the number of customers owing per store. Allocations are
     * summed per debt before the join, so a debt with several allocations is counted once.
     *
     * @param  list<int>  $storeIds
     * @return array<int, array{due: int, debtors: int}>
     */
    public function balancesByStore(array $storeIds): array
    {
        if ($storeIds === []) {
            return [];
        }

        $paidPerDebt = CustomerPaymentAllocation::query()
            ->toBase()
            ->select('customer_debt_id', DB::raw('sum(amount) as paid'))
            ->groupBy('customer_debt_id');

        $duePerCustomer = CustomerDebt::query()
            ->toBase()
            ->leftJoinSub($paidPerDebt, 'paid', 'paid.customer_debt_id', '=', 'customer_debts.id')
            ->whereIn('customer_debts.store_id', $storeIds)
            ->groupBy('customer_debts.store_id', 'customer_debts.customer_id')
            ->select('customer_debts.store_id', DB::raw('sum(customer_debts.original_amount - coalesce(paid.paid, 0)) as due'));

        return DB::query()
            ->fromSub($duePerCustomer, 'accounts')
            ->groupBy('store_id')
            ->select('store_id', DB::raw('sum(case when due > 0 then due else 0 end) as due'), DB::raw('sum(case when due > 0 then 1 else 0 end) as debtors'))
            ->get()
            ->mapWithKeys(fn (object $row): array => [(int) $row->store_id => ['due' => Money::fromDecimal((string) $row->due), 'debtors' => (int) $row->debtors]])
            ->all();
    }

    /**
     * Customer payments taken in cash and waiting at the cashbox, per store. They are reserved
     * against debts but are not allocated, so they do not reduce the balance yet.
     *
     * @param  list<int>  $storeIds
     * @return array<int, array{count: int, amount: int}>
     */
    public function pendingCashPayments(array $storeIds): array
    {
        return CashIntake::query()
            ->whereIn('store_id', $storeIds)
            ->where('source_type', CashIntakeSource::CUSTOMER_PAYMENT)
            ->pending()
            ->toBase()
            ->selectRaw('store_id, count(*) as intakes, sum(amount) as reserved')
            ->groupBy('store_id')
            ->get()
            ->mapWithKeys(fn (object $row): array => [(int) $row->store_id => ['count' => (int) $row->intakes, 'amount' => Money::fromDecimal((string) $row->reserved)]])
            ->all();
    }

    /**
     * Customers of the store with at least one debt in it, with their balance as due_amount.
     *
     * @return Builder<Customer>
     */
    private function accounts(Store $store): Builder
    {
        $owed = CustomerDebt::query()
            ->selectRaw('coalesce(sum(original_amount), 0)')
            ->whereColumn('customer_debts.customer_id', 'customers.id')
            ->where('customer_debts.store_id', $store->id);

        $paid = CustomerPaymentAllocation::query()
            ->selectRaw('coalesce(sum(customer_payment_allocations.amount), 0)')
            ->join('customer_debts', 'customer_debts.id', '=', 'customer_payment_allocations.customer_debt_id')
            ->whereColumn('customer_debts.customer_id', 'customers.id')
            ->where('customer_debts.store_id', $store->id);

        return $store->customers()->getQuery()
            ->select('customers.*')
            ->selectRaw('('.$owed->toSql().') - ('.$paid->toSql().') as due_amount', [...$owed->getBindings(), ...$paid->getBindings()])
            ->whereExists(fn ($query) => $query->select(DB::raw(1))
                ->from('customer_debts')
                ->whereColumn('customer_debts.customer_id', 'customers.id')
                ->where('customer_debts.store_id', $store->id));
    }
}
