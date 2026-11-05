<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Enums\Currency;
use App\Enums\PurchaseStatus;
use App\Models\OperationalSupplier;
use App\Models\Purchase;
use App\Models\Store;
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
        $result = [];

        $ledger = SupplierLedgerEntry::query()
            ->where('operational_supplier_id', $supplier->id)
            ->whereIn('store_id', $storeIds)
            ->groupBy('currency', 'store_id')
            ->select('currency', 'store_id', DB::raw('sum(amount) as balance'))
            ->toBase()
            ->get();

        foreach ($ledger as $row) {
            $minor = Money::fromDecimal((string) $row->balance);
            $result[$row->currency]['balance'] = ($result[$row->currency]['balance'] ?? 0) + $minor;
            $result[$row->currency]['stores'][(int) $row->store_id]['balance'] = $minor;
        }

        $open = Purchase::query()
            ->where('operational_supplier_id', $supplier->id)
            ->whereIn('store_id', $storeIds)
            ->where('status', PurchaseStatus::POSTED)
            ->groupBy('currency', 'store_id')
            ->select('currency', 'store_id', DB::raw('sum(total - paid_total) as outstanding'))
            ->toBase()
            ->get();

        foreach ($open as $row) {
            $minor = Money::fromDecimal((string) $row->outstanding);
            $result[$row->currency]['open_invoices'] = ($result[$row->currency]['open_invoices'] ?? 0) + $minor;
            $result[$row->currency]['stores'][(int) $row->store_id]['open_invoices'] = $minor;
        }

        foreach ($result as $currency => $totals) {
            $result[$currency]['balance'] ??= 0;
            $result[$currency]['open_invoices'] ??= 0;

            foreach ($totals['stores'] ?? [] as $storeId => $store) {
                $result[$currency]['stores'][$storeId] = ['balance' => $store['balance'] ?? 0, 'open_invoices' => $store['open_invoices'] ?? 0];
            }

            $result[$currency]['stores'] ??= [];
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
