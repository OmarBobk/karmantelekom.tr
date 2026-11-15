<?php

declare(strict_types=1);

namespace App\Livewire\Backend\Reports;

use App\Livewire\Backend\Concerns\UsesOperationalLocale;
use App\Models\Store;
use App\Models\User;
use App\Services\Reporting\LocalDateRange;
use App\Services\Reporting\OperationalAuditCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Activitylog\Models\Activity;

/**
 * Owner-only read of recorded operational activity-log events. Area and branch gates apply
 * inside the query before pagination. Sensitive payloads never reach the view.
 */
class AuditLog extends Component
{
    use UsesOperationalLocale;
    use WithPagination;

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    #[Url(except: '')]
    public string $storeId = '';

    #[Url(except: '')]
    public string $area = '';

    #[Url(except: '')]
    public string $event = '';

    #[Url(except: '')]
    public string $actorId = '';

    public function mount(): void
    {
        $this->authorize('viewAuditLog', Store::class);

        $this->from = Str::trim($this->from);
        $this->to = Str::trim($this->to);
        $this->storeId = Str::trim($this->storeId);
        $this->area = Str::trim($this->area);
        $this->event = Str::trim($this->event);
        $this->actorId = Str::trim($this->actorId);

        if ($this->from === '' && $this->to === '') {
            $this->showToday();
        }
    }

    public function applyFilters(): void
    {
        $this->from = Str::trim($this->from);
        $this->to = Str::trim($this->to);
        $this->storeId = Str::trim($this->storeId);
        $this->area = Str::trim($this->area);
        $this->event = Str::trim($this->event);
        $this->actorId = Str::trim($this->actorId);
        $this->resetPage();
    }

    public function showToday(): void
    {
        $this->from = $this->to = LocalDateRange::today();
        $this->resetPage();
    }

    public function updatedStoreId(): void
    {
        $this->resetPage();
    }

    public function updatedArea(): void
    {
        $this->event = '';
        $this->resetPage();
    }

    public function updatedEvent(): void
    {
        $this->resetPage();
    }

    public function updatedActorId(): void
    {
        $this->resetPage();
    }

    #[Layout('layouts.backend')]
    public function render(): View
    {
        $this->authorize('viewAuditLog', Store::class);

        $user = auth()->user();
        $visibleStores = OperationalAuditCatalog::visibleStores($user);
        $eventChoices = OperationalAuditCatalog::visibleEventChoices($user);
        $rangeError = LocalDateRange::error($this->from, $this->to);
        $filterError = $this->filterError($visibleStores, $eventChoices);
        $storesById = Store::query()->whereKey($visibleStores->modelKeys())->get()->keyBy('id');

        $rows = null;
        $actors = collect();

        if ($rangeError === null && $filterError === null) {
            $query = OperationalAuditCatalog::restrictQuery(Activity::query(), $user);
            $this->applyAuthorizedFilters($query, $visibleStores, $eventChoices);
            $actors = $this->authorizedActors(clone $query);
            $rows = $query
                ->with(['causer:id,name'])
                ->select(['id', 'log_name', 'event', 'subject_type', 'subject_id', 'causer_id', 'properties', 'created_at'])
                ->orderByDesc('id')
                ->paginate(OperationalAuditCatalog::PER_PAGE)
                ->through(fn (Activity $activity): array => OperationalAuditCatalog::present($activity, $user, $storesById));
        }

        return view('livewire.backend.reports.audit-log', [
            'rangeError' => $rangeError,
            'filterError' => $filterError,
            'rows' => $rows,
            'visibleStores' => $visibleStores,
            'areas' => collect($eventChoices)->pluck('log')->unique()->values()->all(),
            'eventChoices' => collect($eventChoices)
                ->when($this->area !== '', fn ($choices) => $choices->where('log', $this->area))
                ->values()
                ->all(),
            'actors' => $actors,
            'canViewDashboard' => $user->can('viewOperationsDashboard', Store::class),
            'canViewFinance' => $user->can('viewFinanceOverview', Store::class),
            'canViewReview' => $user->can('viewOperationalReview', Store::class),
            'canViewSales' => $visibleStores->contains(fn (Store $store): bool => $user->can('viewAllSales', $store)),
            'canViewExpenses' => $visibleStores->contains(fn (Store $store): bool => $user->can('manageExpenses', $store)),
            'canViewInventory' => $visibleStores->contains(fn (Store $store): bool => $user->can('viewInventory', $store)),
            ...$this->localeViewData(),
        ])->title(__('reports.audit.title'));
    }

    /**
     * @param  Collection<int, Store>  $visibleStores
     * @param  list<array{log: string, event: string, summary: string}>  $eventChoices
     */
    private function filterError($visibleStores, array $eventChoices): ?string
    {
        if ($this->storeId !== '') {
            if (! ctype_digit($this->storeId) || ! $visibleStores->contains(fn (Store $store): bool => $store->id === (int) $this->storeId)) {
                return 'invalid_store';
            }
        }

        $allowedAreas = collect($eventChoices)->pluck('log')->unique()->all();

        if ($this->area !== '' && ! in_array($this->area, $allowedAreas, true)) {
            return 'invalid_area';
        }

        if ($this->event !== '') {
            $allowed = collect($eventChoices)
                ->when($this->area !== '', fn ($choices) => $choices->where('log', $this->area))
                ->contains(fn (array $choice): bool => $choice['event'] === $this->event);

            if (! $allowed) {
                return 'invalid_event';
            }
        }

        if ($this->actorId !== '' && ! ctype_digit($this->actorId)) {
            return 'invalid_actor';
        }

        return null;
    }

    /**
     * @param  Builder<Activity>  $query
     * @param  Collection<int, Store>  $visibleStores
     * @param  list<array{log: string, event: string, summary: string}>  $eventChoices
     */
    private function applyAuthorizedFilters($query, $visibleStores, array $eventChoices): void
    {
        [$start, $end] = LocalDateRange::utcBounds(LocalDateRange::date($this->from), LocalDateRange::date($this->to));
        $query->where('created_at', '>=', $start)->where('created_at', '<', $end);

        if ($this->storeId !== '') {
            $query->where('properties->store_id', (int) $this->storeId);
        }

        if ($this->area !== '') {
            $query->where('log_name', $this->area);
        }

        if ($this->event !== '') {
            $match = collect($eventChoices)->first(
                fn (array $choice): bool => $choice['event'] === $this->event
                    && ($this->area === '' || $choice['log'] === $this->area)
            );

            if ($match !== null) {
                $query->where('log_name', $match['log'])->where('event', $match['event']);
            }
        }

        if ($this->actorId !== '') {
            $query->where('causer_id', (int) $this->actorId);
        }
    }

    /**
     * Actors that appear in the already-authorized filtered set, excluding the actor filter itself.
     *
     * @param  Builder<Activity>  $query
     * @return Collection<int, User>
     */
    private function authorizedActors($query)
    {
        $actorIds = (clone $query)
            ->reorder()
            ->whereNotNull('causer_id')
            ->distinct()
            ->limit(200)
            ->pluck('causer_id');

        if ($this->actorId !== '' && ctype_digit($this->actorId)) {
            $actorIds = $actorIds->push((int) $this->actorId)->unique();
        }

        return User::query()->whereKey($actorIds)->orderBy('name')->get(['id', 'name']);
    }
}
