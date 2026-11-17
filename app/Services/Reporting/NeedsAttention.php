<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Enums\RepairStatus;
use App\Models\CashboxSession;
use App\Models\DeviceRepair;
use App\Models\NetworkReservation;
use App\Models\OperationalSupplier;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use App\Services\Customers\CustomerAccount;
use App\Services\Purchasing\LocalizedMoney;
use App\Services\Purchasing\Money;
use App\Services\Purchasing\SupplierAccount;
use Illuminate\Support\Collection;

/**
 * Current operational exceptions for the owner dashboard. Each item uses its own permission
 * and ignores the sales period. Zero or absent items are omitted.
 */
final class NeedsAttention
{
    /**
     * @param  Collection<int, Store>  $dashboardStores  Branches already authorized for the dashboard.
     * @return list<array{key: string, store: Store, label: string, detail: string, url: string}>
     */
    public static function for(User $user, Collection $dashboardStores): array
    {
        if ($dashboardStores->isEmpty()) {
            return [];
        }

        $items = [];
        $byId = $dashboardStores->keyBy('id');

        self::appendCashbox($items, $user, $dashboardStores);
        self::appendSupplierMismatches($items, $user, $dashboardStores);
        self::appendPendingSupplierPayouts($items, $user, $dashboardStores, $byId);
        self::appendPendingCustomerCash($items, $user, $dashboardStores, $byId);
        self::appendLapsedReservations($items, $user, $dashboardStores);
        self::appendOldestRepair($items, $user, $dashboardStores);
        self::appendOutOfStock($items, $user, $dashboardStores);

        return $items;
    }

    /**
     * @param  list<array{key: string, store: Store, label: string, detail: string, url: string}>  $items
     * @param  Collection<int, Store>  $stores
     */
    private static function appendCashbox(array &$items, User $user, Collection $stores): void
    {
        foreach ($stores as $store) {
            if (! $user->can('manageCashbox', $store)) {
                continue;
            }

            if (CashboxSession::openFor($store) !== null) {
                continue;
            }

            $tracked = CashboxSession::trackingStarted($store);
            $items[] = [
                'key' => 'cashbox-'.$store->id,
                'store' => $store,
                'label' => $tracked
                    ? __('reports.attention.cashbox_closed', ['branch' => $store->code])
                    : __('reports.attention.cashbox_untracked', ['branch' => $store->code]),
                'detail' => $tracked
                    ? __('reports.attention.cashbox_closed_detail')
                    : __('reports.attention.cashbox_untracked_detail'),
                'url' => route('pos.cashbox', $store),
            ];
        }
    }

    /**
     * @param  list<array{key: string, store: Store, label: string, detail: string, url: string}>  $items
     * @param  Collection<int, Store>  $stores
     */
    private static function appendSupplierMismatches(array &$items, User $user, Collection $stores): void
    {
        if (! $user->can('viewAccounts', OperationalSupplier::class)) {
            return;
        }

        $accounts = app(SupplierAccount::class);
        $rows = $accounts->supplierBalanceRows($stores->modelKeys());

        foreach ($rows as $row) {
            $balance = Money::fromDecimal((string) $row->balance);
            $open = Money::fromDecimal((string) $row->open_invoices);

            if ($balance === $open) {
                continue;
            }

            $store = $stores->first();
            $items[] = [
                'key' => 'supplier-mismatch-'.$row->operational_supplier_id.'-'.$row->currency,
                'store' => $store,
                'label' => __('reports.attention.supplier_mismatch', ['supplier' => $row->name, 'currency' => $row->currency]),
                'detail' => __('reports.attention.supplier_mismatch_detail', [
                    'ledger' => LocalizedMoney::format($balance).' '.$row->currency,
                    'invoices' => LocalizedMoney::format($open).' '.$row->currency,
                ]),
                'url' => route('subdomain.suppliers.account', [(int) $row->operational_supplier_id, 'currency' => $row->currency]),
            ];
        }
    }

    /**
     * @param  list<array{key: string, store: Store, label: string, detail: string, url: string}>  $items
     * @param  Collection<int, Store>  $stores
     * @param  Collection<int, Store>  $byId
     */
    private static function appendPendingSupplierPayouts(array &$items, User $user, Collection $stores, Collection $byId): void
    {
        if (! $user->can('viewAccounts', OperationalSupplier::class)) {
            return;
        }

        $pending = app(SupplierAccount::class)->pendingCashPayouts($stores->modelKeys());

        foreach ($pending as $storeId => $row) {
            if ($row['count'] === 0 || ! isset($byId[$storeId])) {
                continue;
            }

            $store = $byId[$storeId];
            $items[] = [
                'key' => 'supplier-payout-'.$storeId,
                'store' => $store,
                'label' => __('reports.attention.supplier_payouts', ['branch' => $store->code]),
                'detail' => trans_choice('reports.attention.pending_amount', $row['count'], [
                    'count' => $row['count'],
                    'amount' => LocalizedMoney::format($row['amount']),
                ]),
                'url' => route('pos.cashbox', $store),
            ];
        }
    }

    /**
     * @param  list<array{key: string, store: Store, label: string, detail: string, url: string}>  $items
     * @param  Collection<int, Store>  $stores
     * @param  Collection<int, Store>  $byId
     */
    private static function appendPendingCustomerCash(array &$items, User $user, Collection $stores, Collection $byId): void
    {
        $allowed = $stores->filter(fn (Store $store): bool => $user->can('receiveCustomerPayments', $store))->values();

        if ($allowed->isEmpty()) {
            return;
        }

        $pending = app(CustomerAccount::class)->pendingCashPayments($allowed->modelKeys());

        foreach ($pending as $storeId => $row) {
            if ($row['count'] === 0 || ! isset($byId[$storeId])) {
                continue;
            }

            $store = $byId[$storeId];
            $items[] = [
                'key' => 'customer-cash-'.$storeId,
                'store' => $store,
                'label' => __('reports.attention.customer_cash', ['branch' => $store->code]),
                'detail' => trans_choice('reports.attention.pending_amount', $row['count'], [
                    'count' => $row['count'],
                    'amount' => LocalizedMoney::format($row['amount']),
                ]),
                'url' => route('pos.customers', $store),
            ];
        }
    }

    /**
     * @param  list<array{key: string, store: Store, label: string, detail: string, url: string}>  $items
     * @param  Collection<int, Store>  $stores
     */
    private static function appendLapsedReservations(array &$items, User $user, Collection $stores): void
    {
        foreach ($stores as $store) {
            if (! $user->can('manageReservations', $store)) {
                continue;
            }

            $count = NetworkReservation::query()
                ->where('owning_store_id', $store->id)
                ->currentlyLapsed()
                ->count();

            if ($count === 0) {
                continue;
            }

            $items[] = [
                'key' => 'lapsed-'.$store->id,
                'store' => $store,
                'label' => __('reports.attention.lapsed', ['branch' => $store->code]),
                'detail' => trans_choice('reports.attention.lapsed_detail', $count, ['count' => $count]),
                'url' => route('pos.reservations', [$store, 'tab' => 'incoming', 'state' => NetworkReservation::CURRENTLY_LAPSED]),
            ];
        }
    }

    /**
     * @param  list<array{key: string, store: Store, label: string, detail: string, url: string}>  $items
     * @param  Collection<int, Store>  $stores
     */
    private static function appendOldestRepair(array &$items, User $user, Collection $stores): void
    {
        $allowed = $stores->filter(fn (Store $store): bool => $user->can('viewInventory', $store))->values();

        if ($allowed->isEmpty()) {
            return;
        }

        $repair = DeviceRepair::query()
            ->whereIn('store_id', $allowed->modelKeys())
            ->where('status', RepairStatus::OPEN)
            ->with(['store:id,code,name', 'unit.productDefinition:id,name'])
            ->orderBy('started_at')
            ->orderBy('id')
            ->first();

        if ($repair === null) {
            return;
        }

        $store = $repair->store;
        $started = $repair->started_at;
        $hours = max(0, (int) $started->diffInHours(now()));
        $url = $user->can('viewInventory', $store) && $repair->unit !== null
            ? route('subdomain.inventory.unit', [$store, $repair->unit])
            : route('subdomain.inventory.store', [$store, 'units' => 'in_repair']);

        $items[] = [
            'key' => 'repair-'.$repair->id,
            'store' => $store,
            'label' => __('reports.attention.oldest_repair', [
                'branch' => $store->code,
                'name' => $repair->unit?->productDefinition?->name ?? '—',
            ]),
            'detail' => __('reports.attention.oldest_repair_detail', [
                'started' => Sale::displayTime($started),
                'hours' => $hours,
            ]),
            'url' => $url,
        ];
    }

    /**
     * @param  list<array{key: string, store: Store, label: string, detail: string, url: string}>  $items
     * @param  Collection<int, Store>  $stores
     */
    private static function appendOutOfStock(array &$items, User $user, Collection $stores): void
    {
        $allowed = $stores->filter(fn (Store $store): bool => $user->can('viewInventory', $store))->values();

        if ($allowed->isEmpty()) {
            return;
        }

        $counts = OutOfStock::countsByStore($allowed->modelKeys());

        foreach ($allowed as $store) {
            $count = $counts[$store->id] ?? 0;

            if ($count === 0) {
                continue;
            }

            $items[] = [
                'key' => 'out-of-stock-'.$store->id,
                'store' => $store,
                'label' => __('reports.attention.out_of_stock', ['branch' => $store->code]),
                'detail' => trans_choice('reports.attention.out_of_stock_detail', $count, ['count' => $count]),
                'url' => route('subdomain.inventory.store', [$store, 'stock' => 'out']),
            ];
        }
    }
}
