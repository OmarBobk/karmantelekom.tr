<?php

declare(strict_types=1);

namespace App\Livewire\Backend\Reports;

use App\Enums\RepairStatus;
use App\Enums\TrackingMode;
use App\Enums\UnitStatus;
use App\Livewire\Backend\Concerns\UsesOperationalLocale;
use App\Models\DeviceCostEntry;
use App\Models\DeviceRepair;
use App\Models\InventoryUnit;
use App\Models\NetworkReservation;
use App\Models\PurchaseReceiptUnit;
use App\Models\Store;
use App\Models\User;
use App\Services\Purchasing\Money;
use App\Services\Reporting\LocalDateRange;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Owner operational review: Store Network activity and device/repair status for an Istanbul
 * date range. Current figures ignore the range; period figures use the timestamps documented
 * in the plan. Cost amounts appear only for branches with manageDeviceCosts.
 */
class OperationalReview extends Component
{
    use UsesOperationalLocale;
    use WithPagination;

    public const PER_PAGE = 25;

    /** @var list<string> */
    private const CURRENT_STATES = [
        'pending',
        'approved',
        'payment_started',
        'in_transit',
        'received',
        NetworkReservation::CURRENTLY_LAPSED,
    ];

    /** @var list<UnitStatus> */
    private const ON_HAND_STATUSES = [
        UnitStatus::AVAILABLE,
        UnitStatus::INSPECTION,
        UnitStatus::IN_REPAIR,
        UnitStatus::REVIEW,
        UnitStatus::IN_TRANSIT,
    ];

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    public function mount(): void
    {
        $this->authorize('viewOperationalReview', Store::class);

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
        $this->resetPage('openRepairs');
        $this->resetPage('closedRepairs');
    }

    public function showToday(): void
    {
        $this->from = $this->to = LocalDateRange::today();
        $this->resetPage('openRepairs');
        $this->resetPage('closedRepairs');
    }

    #[Layout('layouts.backend')]
    public function render(): View
    {
        $this->authorize('viewOperationalReview', Store::class);

        $user = auth()->user();
        $stores = Store::query()->accessibleTo($user)->orderBy('code')->get();
        $rangeError = LocalDateRange::error($this->from, $this->to);
        $asOf = now();
        $networkStores = $this->storesWhere($stores, $user, 'manageReservations');
        $deviceStores = $this->storesWhere($stores, $user, 'viewInventory');
        $costStoreIds = $deviceStores
            ->filter(fn (Store $store): bool => $user->can('manageDeviceCosts', $store))
            ->modelKeys();

        return view('livewire.backend.reports.operational-review', [
            'rangeError' => $rangeError,
            'asOf' => $asOf,
            'network' => $this->network($networkStores, $rangeError, $asOf),
            'devices' => $this->devices($deviceStores, $costStoreIds, $rangeError, $asOf),
            'canViewDashboard' => $user->can('viewOperationsDashboard', Store::class),
            'canViewFinance' => $user->can('viewFinanceOverview', Store::class),
            'canViewAudit' => $user->can('viewAuditLog', Store::class),
            ...$this->localeViewData(),
        ])->title(__('reports.review.title'));
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
     * @param  Collection<int, Store>  $stores
     * @return array{incoming: list<array<string, mixed>>, outgoing: list<array<string, mixed>>, totals: array<string, int>, period_totals: ?array<string, int>}|null
     */
    private function network(Collection $stores, ?string $rangeError, CarbonInterface $asOf): ?array
    {
        if ($stores->isEmpty()) {
            return null;
        }

        $storeIds = $stores->modelKeys();
        $incomingCurrent = $this->networkCurrentCounts($storeIds, 'owning_store_id', $asOf);
        $outgoingCurrent = $this->networkCurrentCounts($storeIds, 'requesting_store_id', $asOf);
        $incomingPeriod = $rangeError === null
            ? $this->networkPeriodCounts($storeIds, 'owning_store_id', $asOf)
            : null;
        $outgoingPeriod = $rangeError === null
            ? $this->networkPeriodCounts($storeIds, 'requesting_store_id', $asOf)
            : null;
        $cancellationDetail = $rangeError === null
            ? $this->cancellationPerformers($storeIds, $asOf)
            : null;

        $incoming = $stores->map(fn (Store $store): array => [
            'store' => $store,
            'current' => $incomingCurrent[$store->id] ?? $this->emptyCurrentCounts(),
            'period' => $incomingPeriod[$store->id] ?? ($rangeError === null ? $this->emptyPeriodCounts() : null),
            'cancelled_by_owning' => $cancellationDetail['incoming'][$store->id]['by_owning'] ?? 0,
            'cancelled_by_requesting' => $cancellationDetail['incoming'][$store->id]['by_requesting'] ?? 0,
        ])->all();

        $outgoing = $stores->map(fn (Store $store): array => [
            'store' => $store,
            'current' => $outgoingCurrent[$store->id] ?? $this->emptyCurrentCounts(),
            'period' => $outgoingPeriod[$store->id] ?? ($rangeError === null ? $this->emptyPeriodCounts() : null),
            'cancelled_by_owning' => $cancellationDetail['outgoing'][$store->id]['by_owning'] ?? 0,
            'cancelled_by_requesting' => $cancellationDetail['outgoing'][$store->id]['by_requesting'] ?? 0,
        ])->all();

        return [
            'incoming' => $incoming,
            'outgoing' => $outgoing,
            'totals' => $this->sumCountMaps(array_column($incoming, 'current')),
            'period_totals' => $rangeError === null
                ? $this->sumCountMaps(array_column($incoming, 'period'))
                : null,
        ];
    }

    /**
     * @param  list<int>  $storeIds
     * @return array<int, array<string, int>>
     */
    private function networkCurrentCounts(array $storeIds, string $branchColumn, CarbonInterface $asOf): array
    {
        $counts = [];

        foreach ($storeIds as $storeId) {
            $counts[$storeId] = $this->emptyCurrentCounts();
        }

        foreach (self::CURRENT_STATES as $state) {
            $rows = NetworkReservation::query()
                ->when(
                    $state === NetworkReservation::CURRENTLY_LAPSED,
                    fn ($query) => $query->currentlyLapsed($asOf),
                    fn ($query) => $query->inDisplayState($state, $asOf),
                )
                ->whereIn($branchColumn, $storeIds)
                ->toBase()
                ->selectRaw("{$branchColumn} as store_id, count(*) as aggregate")
                ->groupBy($branchColumn)
                ->get();

            foreach ($rows as $row) {
                $counts[(int) $row->store_id][$state] = (int) $row->aggregate;
            }
        }

        return $counts;
    }

    /**
     * @param  list<int>  $storeIds
     * @return array<int, array<string, int>>
     */
    private function networkPeriodCounts(array $storeIds, string $branchColumn, CarbonInterface $asOf): array
    {
        $from = LocalDateRange::date($this->from);
        $to = LocalDateRange::date($this->to);
        $counts = [];

        foreach ($storeIds as $storeId) {
            $counts[$storeId] = $this->emptyPeriodCounts();
        }

        foreach (NetworkReservation::PERIOD_EVENTS as $event) {
            $rows = NetworkReservation::query()
                ->forPeriodEvent($event, $from, $to, $asOf)
                ->whereIn($branchColumn, $storeIds)
                ->toBase()
                ->selectRaw("{$branchColumn} as store_id, count(*) as aggregate")
                ->groupBy($branchColumn)
                ->get();

            foreach ($rows as $row) {
                $counts[(int) $row->store_id][$event] = (int) $row->aggregate;
            }
        }

        return $counts;
    }

    /**
     * Who performed cancellations in the period, still grouped by owning/requesting branch.
     *
     * @param  list<int>  $storeIds
     * @return array{incoming: array<int, array{by_owning: int, by_requesting: int}>, outgoing: array<int, array{by_owning: int, by_requesting: int}>}
     */
    private function cancellationPerformers(array $storeIds, CarbonInterface $asOf): array
    {
        $from = LocalDateRange::date($this->from);
        $to = LocalDateRange::date($this->to);
        $empty = [];

        foreach ($storeIds as $storeId) {
            $empty[$storeId] = ['by_owning' => 0, 'by_requesting' => 0];
        }

        $result = ['incoming' => $empty, 'outgoing' => $empty];

        $rows = NetworkReservation::query()
            ->forPeriodEvent('cancelled', $from, $to, $asOf)
            ->where(fn (Builder $query) => $query->whereIn('owning_store_id', $storeIds)->orWhereIn('requesting_store_id', $storeIds))
            ->toBase()
            ->selectRaw('owning_store_id, requesting_store_id, cancelled_store_id, count(*) as aggregate')
            ->groupBy('owning_store_id', 'requesting_store_id', 'cancelled_store_id')
            ->get();

        foreach ($rows as $row) {
            $owning = (int) $row->owning_store_id;
            $requesting = (int) $row->requesting_store_id;
            $cancelledBy = (int) $row->cancelled_store_id;
            $count = (int) $row->aggregate;
            $bucket = $cancelledBy === $owning ? 'by_owning' : 'by_requesting';

            if (isset($result['incoming'][$owning])) {
                $result['incoming'][$owning][$bucket] += $count;
            }

            if (isset($result['outgoing'][$requesting])) {
                $result['outgoing'][$requesting][$bucket] += $count;
            }
        }

        return $result;
    }

    /**
     * @param  Collection<int, Store>  $stores
     * @param  list<int>  $costStoreIds
     * @return array{
     *     status_rows: list<array{store: Store, counts: array<string, int>}>,
     *     status_totals: array<string, int>,
     *     open_repairs: LengthAwarePaginator,
     *     closed_counts: array<int, array{completed: int, cancelled: int}>,
     *     closed_totals: array{completed: int, cancelled: int},
     *     closed_repairs: LengthAwarePaginator,
     *     costs: ?array<string, mixed>
     * }|null
     */
    private function devices(Collection $stores, array $costStoreIds, ?string $rangeError, CarbonInterface $asOf): ?array
    {
        if ($stores->isEmpty()) {
            return null;
        }

        $storeIds = $stores->modelKeys();
        $statusCounts = $this->unitStatusCounts($storeIds);
        $statusRows = $stores->map(fn (Store $store): array => [
            'store' => $store,
            'counts' => $statusCounts[$store->id] ?? $this->emptyStatusCounts(),
        ])->all();

        $closedCounts = $rangeError === null
            ? $this->closedRepairCounts($storeIds)
            : array_fill_keys($storeIds, ['completed' => 0, 'cancelled' => 0]);

        return [
            'status_rows' => $statusRows,
            'status_totals' => $this->sumCountMaps(array_column($statusRows, 'counts')),
            'open_repairs' => $this->openRepairs($storeIds, $costStoreIds, $asOf),
            'closed_counts' => $closedCounts,
            'closed_totals' => [
                'completed' => array_sum(array_column($closedCounts, 'completed')),
                'cancelled' => array_sum(array_column($closedCounts, 'cancelled')),
            ],
            'closed_repairs' => $rangeError === null
                ? $this->closedRepairs($storeIds, $costStoreIds)
                : new LengthAwarePaginator([], 0, self::PER_PAGE, 1, ['pageName' => 'closedRepairs']),
            'costs' => $costStoreIds === [] ? null : $this->recordedCosts($costStoreIds, $stores->keyBy('id')),
        ];
    }

    /**
     * @param  list<int>  $storeIds
     * @return array<int, array<string, int>>
     */
    private function unitStatusCounts(array $storeIds): array
    {
        $counts = [];

        foreach ($storeIds as $storeId) {
            $counts[$storeId] = $this->emptyStatusCounts();
        }

        $rows = InventoryUnit::query()
            ->whereIn('store_id', $storeIds)
            ->whereIn('status', self::ON_HAND_STATUSES)
            ->toBase()
            ->selectRaw('store_id, status, count(*) as aggregate')
            ->groupBy('store_id', 'status')
            ->get();

        foreach ($rows as $row) {
            $counts[(int) $row->store_id][$row->status] = (int) $row->aggregate;
        }

        return $counts;
    }

    /**
     * @param  list<int>  $storeIds
     * @param  list<int>  $costStoreIds
     */
    private function openRepairs(array $storeIds, array $costStoreIds, CarbonInterface $asOf): LengthAwarePaginator
    {
        $costIds = array_flip($costStoreIds);

        $user = auth()->user();
        $page = DeviceRepair::query()
            ->whereIn('store_id', $storeIds)
            ->where('status', RepairStatus::OPEN)
            ->with([
                'store:id,code,name',
                'starter:id,name',
                'unit:id,store_id,product_definition_id,status',
                'unit.store:id,code,name',
                'unit.productDefinition:id,name,model',
                'unit.identifiers',
            ])
            ->latest('started_at')
            ->latest('id')
            ->paginate(self::PER_PAGE, ['*'], 'openRepairs');

        $summaries = $this->repairCostSummary(
            $page->getCollection()
                ->filter(fn (DeviceRepair $repair): bool => isset($costIds[$repair->store_id]))
                ->modelKeys()
        );

        return $page->through(function (DeviceRepair $repair) use ($costIds, $asOf, $summaries, $user): array {
            return [
                'repair' => $repair,
                'age_hours' => $repair->started_at->diffInHours($asOf),
                'device_url' => $this->deviceUrl($repair, $user),
                'costs' => isset($costIds[$repair->store_id])
                    ? ($summaries[$repair->id] ?? $this->emptyRepairCostSummary())
                    : null,
            ];
        });
    }

    /**
     * @param  list<int>  $storeIds
     * @return array<int, array{completed: int, cancelled: int}>
     */
    private function closedRepairCounts(array $storeIds): array
    {
        $from = LocalDateRange::date($this->from);
        $to = LocalDateRange::date($this->to);
        [$start, $end] = LocalDateRange::utcBounds($from, $to);
        $counts = [];

        foreach ($storeIds as $storeId) {
            $counts[$storeId] = ['completed' => 0, 'cancelled' => 0];
        }

        $rows = DeviceRepair::query()
            ->whereIn('store_id', $storeIds)
            ->whereIn('status', [RepairStatus::COMPLETED, RepairStatus::CANCELLED])
            ->where('closed_at', '>=', $start)
            ->where('closed_at', '<', $end)
            ->toBase()
            ->selectRaw('store_id, status, count(*) as aggregate')
            ->groupBy('store_id', 'status')
            ->get();

        foreach ($rows as $row) {
            $counts[(int) $row->store_id][$row->status] = (int) $row->aggregate;
        }

        return $counts;
    }

    /**
     * @param  list<int>  $storeIds
     * @param  list<int>  $costStoreIds
     */
    private function closedRepairs(array $storeIds, array $costStoreIds): LengthAwarePaginator
    {
        $from = LocalDateRange::date($this->from);
        $to = LocalDateRange::date($this->to);
        [$start, $end] = LocalDateRange::utcBounds($from, $to);
        $costIds = array_flip($costStoreIds);

        $user = auth()->user();
        $page = DeviceRepair::query()
            ->whereIn('store_id', $storeIds)
            ->whereIn('status', [RepairStatus::COMPLETED, RepairStatus::CANCELLED])
            ->where('closed_at', '>=', $start)
            ->where('closed_at', '<', $end)
            ->with([
                'store:id,code,name',
                'starter:id,name',
                'closer:id,name',
                'unit:id,store_id,product_definition_id,status',
                'unit.store:id,code,name',
                'unit.productDefinition:id,name,model',
                'unit.identifiers',
            ])
            ->latest('closed_at')
            ->latest('id')
            ->paginate(self::PER_PAGE, ['*'], 'closedRepairs');

        $summaries = $this->repairCostSummary(
            $page->getCollection()
                ->filter(fn (DeviceRepair $repair): bool => isset($costIds[$repair->store_id]))
                ->modelKeys()
        );

        return $page->through(fn (DeviceRepair $repair): array => [
            'repair' => $repair,
            'device_url' => $this->deviceUrl($repair, $user),
            'costs' => isset($costIds[$repair->store_id])
                ? ($summaries[$repair->id] ?? $this->emptyRepairCostSummary())
                : null,
        ]);
    }

    /**
     * Link to the device at its current location when the viewer may open that branch's inventory.
     * Repair history stays attributed to the repair branch; a missing or unauthorized device is plain text.
     */
    private function deviceUrl(DeviceRepair $repair, User $user): ?string
    {
        $unit = $repair->unit;
        $currentStore = $unit?->store;

        if ($unit === null || $currentStore === null || ! $user->can('viewInventory', $currentStore)) {
            return null;
        }

        return route('subdomain.inventory.unit', [$currentStore, $unit]);
    }

    /**
     * Active (non-reversed) cost totals for the given repairs, without joining into the repair query.
     *
     * @param  list<int>  $repairIds
     * @return array<int, array{known: array<string, int>, unknown: int, has_entries: bool}>
     */
    private function repairCostSummary(array $repairIds): array
    {
        if ($repairIds === []) {
            return [];
        }

        $summaries = [];

        foreach ($repairIds as $repairId) {
            $summaries[$repairId] = $this->emptyRepairCostSummary();
        }

        $rows = DeviceCostEntry::query()
            ->whereIn('device_repair_id', $repairIds)
            ->whereNull('reversed_at')
            ->toBase()
            ->selectRaw('device_repair_id, currency, count(*) as entry_count, coalesce(sum(amount), 0) as amount_total, sum(case when amount is null then 1 else 0 end) as unknown_count')
            ->groupBy('device_repair_id', 'currency')
            ->get();

        foreach ($rows as $row) {
            $repairId = (int) $row->device_repair_id;
            $summaries[$repairId]['has_entries'] = true;
            $summaries[$repairId]['unknown'] += (int) $row->unknown_count;

            if ($row->currency !== null) {
                $summaries[$repairId]['known'][$row->currency] = Money::fromDecimal((string) $row->amount_total);
            }
        }

        return $summaries;
    }

    /**
     * Recorded costs for used devices still on hand. Acquisition and additional costs are queried separately.
     *
     * @param  list<int>  $storeIds
     * @param  Collection<int, Store>  $storesById
     * @return array{rows: list<array<string, mixed>>, totals: array<string, mixed>}
     */
    private function recordedCosts(array $storeIds, Collection $storesById): array
    {
        $onHand = InventoryUnit::query()
            ->whereIn('store_id', $storeIds)
            ->whereIn('status', self::ON_HAND_STATUSES)
            ->whereHas('productDefinition', fn (Builder $definition) => $definition->where('tracking_mode', TrackingMode::USED_DEVICE));

        $deviceCounts = (clone $onHand)
            ->toBase()
            ->selectRaw('store_id, count(*) as device_count')
            ->groupBy('store_id')
            ->pluck('device_count', 'store_id');

        $acquisitionKnown = PurchaseReceiptUnit::query()
            ->whereIn('inventory_unit_id', (clone $onHand)->select('inventory_units.id'))
            ->join('inventory_units', 'inventory_units.id', '=', 'purchase_receipt_units.inventory_unit_id')
            ->toBase()
            ->selectRaw('inventory_units.store_id, purchase_receipt_units.currency, count(*) as device_count, coalesce(sum(purchase_receipt_units.unit_cost), 0) as amount_total')
            ->groupBy('inventory_units.store_id', 'purchase_receipt_units.currency')
            ->get();

        $unitsWithAcquisition = PurchaseReceiptUnit::query()
            ->whereIn('inventory_unit_id', (clone $onHand)->select('inventory_units.id'))
            ->join('inventory_units', 'inventory_units.id', '=', 'purchase_receipt_units.inventory_unit_id')
            ->toBase()
            ->selectRaw('inventory_units.store_id, count(distinct purchase_receipt_units.inventory_unit_id) as device_count')
            ->groupBy('inventory_units.store_id')
            ->pluck('device_count', 'store_id');

        $additional = DeviceCostEntry::query()
            ->whereNull('reversed_at')
            ->whereIn('inventory_unit_id', (clone $onHand)->select('inventory_units.id'))
            ->join('inventory_units', 'inventory_units.id', '=', 'device_cost_entries.inventory_unit_id')
            ->toBase()
            ->selectRaw('inventory_units.store_id, device_cost_entries.currency, coalesce(sum(device_cost_entries.amount), 0) as amount_total, sum(case when device_cost_entries.amount is null then 1 else 0 end) as unknown_count')
            ->groupBy('inventory_units.store_id', 'device_cost_entries.currency')
            ->get();

        $rows = [];
        $totals = [
            'devices' => 0,
            'unknown_acquisition' => 0,
            'acquisition' => [],
            'additional' => [],
            'additional_unknown' => 0,
        ];

        foreach ($storeIds as $storeId) {
            $devices = (int) ($deviceCounts[$storeId] ?? 0);
            $knownAcquisitionDevices = (int) ($unitsWithAcquisition[$storeId] ?? 0);
            $unknownAcquisition = max(0, $devices - $knownAcquisitionDevices);
            $acquisition = [];
            $additionalKnown = [];
            $additionalUnknown = 0;

            foreach ($acquisitionKnown->where('store_id', $storeId) as $row) {
                $acquisition[$row->currency] = Money::fromDecimal((string) $row->amount_total);
                $totals['acquisition'][$row->currency] = ($totals['acquisition'][$row->currency] ?? 0) + $acquisition[$row->currency];
            }

            foreach ($additional->where('store_id', $storeId) as $row) {
                $additionalUnknown += (int) $row->unknown_count;

                if ($row->currency !== null) {
                    $additionalKnown[$row->currency] = Money::fromDecimal((string) $row->amount_total);
                    $totals['additional'][$row->currency] = ($totals['additional'][$row->currency] ?? 0) + $additionalKnown[$row->currency];
                }
            }

            $rows[] = [
                'store' => $storesById->get($storeId),
                'devices' => $devices,
                'unknown_acquisition' => $unknownAcquisition,
                'acquisition' => $acquisition,
                'additional' => $additionalKnown,
                'additional_unknown' => $additionalUnknown,
            ];

            $totals['devices'] += $devices;
            $totals['unknown_acquisition'] += $unknownAcquisition;
            $totals['additional_unknown'] += $additionalUnknown;
        }

        return ['rows' => $rows, 'totals' => $totals];
    }

    /**
     * @return array<string, int>
     */
    private function emptyCurrentCounts(): array
    {
        return array_fill_keys(self::CURRENT_STATES, 0);
    }

    /**
     * @return array<string, int>
     */
    private function emptyPeriodCounts(): array
    {
        return array_fill_keys(NetworkReservation::PERIOD_EVENTS, 0);
    }

    /**
     * @return array<string, int>
     */
    private function emptyStatusCounts(): array
    {
        return [
            UnitStatus::AVAILABLE->value => 0,
            UnitStatus::INSPECTION->value => 0,
            UnitStatus::IN_REPAIR->value => 0,
            UnitStatus::REVIEW->value => 0,
            UnitStatus::IN_TRANSIT->value => 0,
        ];
    }

    /**
     * @return array{known: array<string, int>, unknown: int, has_entries: bool}
     */
    private function emptyRepairCostSummary(): array
    {
        return ['known' => [], 'unknown' => 0, 'has_entries' => false];
    }

    /**
     * @param  list<array<string, int>|null>  $maps
     * @return array<string, int>
     */
    private function sumCountMaps(array $maps): array
    {
        $totals = [];

        foreach ($maps as $map) {
            if ($map === null) {
                continue;
            }

            foreach ($map as $key => $value) {
                $totals[$key] = ($totals[$key] ?? 0) + $value;
            }
        }

        return $totals;
    }
}
