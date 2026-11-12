<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Enums\Currency;
use App\Enums\PurchaseStatus;
use App\Models\OperationalSupplier;
use App\Models\Purchase;
use App\Models\Store;
use App\Models\SupplierCashPayout;
use App\Models\SupplierLedgerEntry;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Read model for a supplier's account, limited to the stores the viewer can access.
 *
 * Balances come from the append-only ledger; open-invoice totals come from posted
 * invoices. The two must agree per currency and store.
 */
class SupplierAccount
{
    /**
     * @return list<int>
     */
    public function accessibleStoreIds(User $user): array
    {
        return Store::query()->accessibleTo($user)->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }

    /**
     * Ledger balance and open-invoice total per currency, with a per-store breakdown.
     *
     * @param  list<int>  $storeIds
     * @return array<string, array{balance: int, open_invoices: int, stores: array<int, array{balance: int, open_invoices: int}>}>
     */
    public function balances(OperationalSupplier $supplier, array $storeIds): array
    {
        return $this->aggregate($supplier, $storeIds);
    }

    /**
     * The same balances across every supplier: what the stores owe suppliers, per currency.
     *
     * @param  list<int>  $storeIds
     * @return array<string, array{balance: int, open_invoices: int, stores: array<int, array{balance: int, open_invoices: int}>}>
     */
    public function allBalances(array $storeIds): array
    {
        return $this->aggregate(null, $storeIds);
    }

    /**
     * Suppliers with a non-zero ledger balance in the stores, one row per supplier and currency,
     * with the open-invoice total for the same supplier, currency, and stores.
     *
     * @param  list<int>  $storeIds
     * @return LengthAwarePaginator<int, object{operational_supplier_id: int, name: string, currency: string, balance: string, open_invoices: string}>
     */
    public function supplierBalances(array $storeIds, int $perPage = 25, string $pageName = 'page'): LengthAwarePaginator
    {
        $openInvoices = Purchase::query()
            ->selectRaw('coalesce(sum(total - paid_total), 0)')
            ->whereColumn('purchases.operational_supplier_id', 'balances.operational_supplier_id')
            ->whereColumn('purchases.currency', 'balances.currency')
            ->whereIn('purchases.store_id', $storeIds)
            ->where('purchases.status', PurchaseStatus::POSTED);

        $balances = SupplierLedgerEntry::query()
            ->whereIn('store_id', $storeIds)
            ->groupBy('operational_supplier_id', 'currency')
            ->havingRaw('sum(amount) <> 0')
            ->select('operational_supplier_id', 'currency', DB::raw('sum(amount) as balance'))
            ->toBase();

        return DB::query()
            ->fromSub($balances, 'balances')
            ->join('operational_suppliers', 'operational_suppliers.id', '=', 'balances.operational_supplier_id')
            ->select('balances.operational_supplier_id', 'operational_suppliers.name', 'balances.currency', 'balances.balance')
            ->selectSub($openInvoices->toBase(), 'open_invoices')
            ->orderBy('operational_suppliers.name')
            ->orderBy('balances.operational_supplier_id')
            ->orderBy('balances.currency')
            ->paginate($perPage, pageName: $pageName);
    }

    /**
     * TRY cash reserved by pending supplier cash payouts per store. Nothing is paid until a
     * payout is confirmed, so these never reduce a balance.
     *
     * @param  list<int>  $storeIds
     * @return array<int, array{count: int, amount: int}>
     */
    public function pendingCashPayouts(array $storeIds): array
    {
        return SupplierCashPayout::query()
            ->whereIn('store_id', $storeIds)
            ->pending()
            ->toBase()
            ->selectRaw('store_id, count(*) as payouts, sum(amount) as reserved')
            ->groupBy('store_id')
            ->get()
            ->mapWithKeys(fn (object $row): array => [(int) $row->store_id => ['count' => (int) $row->payouts, 'amount' => Money::fromDecimal((string) $row->reserved)]])
            ->all();
    }

    /**
     * Ledger balance (append-only entries, reversals included) and open-invoice total per currency
     * and store, for one supplier or all of them.
     *
     * @param  list<int>  $storeIds
     * @return array<string, array{balance: int, open_invoices: int, stores: array<int, array{balance: int, open_invoices: int}>}>
     */
    private function aggregate(?OperationalSupplier $supplier, array $storeIds): array
    {
        $stores = [];

        $ledger = SupplierLedgerEntry::query()
            ->when($supplier, fn ($query) => $query->where('operational_supplier_id', $supplier->id))
            ->whereIn('store_id', $storeIds)
            ->groupBy('currency', 'store_id')
            ->select('currency', 'store_id', DB::raw('sum(amount) as balance'))
            ->toBase()
            ->get();

        foreach ($ledger as $row) {
            $stores[$row->currency][(int) $row->store_id]['balance'] = Money::fromDecimal((string) $row->balance);
        }

        $open = Purchase::query()
            ->when($supplier, fn ($query) => $query->where('operational_supplier_id', $supplier->id))
            ->whereIn('store_id', $storeIds)
            ->where('status', PurchaseStatus::POSTED)
            ->groupBy('currency', 'store_id')
            ->select('currency', 'store_id', DB::raw('sum(total - paid_total) as outstanding'))
            ->toBase()
            ->get();

        foreach ($open as $row) {
            $stores[$row->currency][(int) $row->store_id]['open_invoices'] = Money::fromDecimal((string) $row->outstanding);
        }

        $result = [];

        foreach ($stores as $currency => $byStore) {
            $byStore = array_map(fn (array $store): array => ['balance' => $store['balance'] ?? 0, 'open_invoices' => $store['open_invoices'] ?? 0], $byStore);
            ksort($byStore);

            $result[$currency] = [
                'balance' => Money::sumSigned(array_column($byStore, 'balance')),
                'open_invoices' => Money::sumSigned(array_column($byStore, 'open_invoices')),
                'stores' => $byStore,
            ];
        }

        ksort($result);

        return $result;
    }

    /**
     * Newest-first ledger page with the running balance after each entry.
     *
     * Running balances accumulate oldest-first by (occurred_at, id), so entries sharing a
     * timestamp always appear in the same order.
     *
     * @param  list<int>  $storeIds
     * @return LengthAwarePaginator<int, SupplierLedgerEntry>
     */
    public function statement(OperationalSupplier $supplier, Currency $currency, array $storeIds, int $perPage = 25, string $pageName = 'page'): LengthAwarePaginator
    {
        return SupplierLedgerEntry::query()
            ->where('operational_supplier_id', $supplier->id)
            ->where('currency', $currency)
            ->whereIn('store_id', $storeIds)
            ->select('supplier_ledger_entries.*')
            ->selectRaw('sum(amount) over (order by occurred_at, id) as running_balance')
            ->with(['purchase:id,supplier_invoice_number,status,store_id', 'store:id,code', 'actor:id,name'])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate($perPage, pageName: $pageName);
    }
}
