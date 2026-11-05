<?php

declare(strict_types=1);

namespace App\Livewire\Backend\Purchases;

use App\Actions\Purchasing\CreatePurchaseDraft;
use App\Enums\Currency;
use App\Enums\PurchaseStatus;
use App\Enums\PurchaseTaxTreatment;
use App\Livewire\Backend\Concerns\UsesOperationalLocale;
use App\Models\OperationalSupplier;
use App\Models\Purchase;
use App\Models\Store;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Purchase invoices across the stores the owner can access, with server-side filters.
 */
class PurchasesIndex extends Component
{
    use UsesOperationalLocale;
    use WithPagination;

    public const VIEWS = ['all', 'draft', 'awaiting_receipt', 'outstanding', 'settled', 'reversed', 'cancelled'];

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: 'all')]
    public string $view = 'all';

    #[Url(except: '')]
    public string $store = '';

    public string $newStoreId = '';

    public string $newSupplierId = '';

    public string $newCurrency = 'TRY';

    public string $newTaxTreatment = 'kdv_included';

    public function mount(): void
    {
        $this->authorize('viewAny', Purchase::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'view', 'store'], true)) {
            $this->resetPage();
        }
    }

    public function createDraft(CreatePurchaseDraft $createPurchaseDraft): void
    {
        $this->authorize('create', Purchase::class);

        try {
            $purchase = $createPurchaseDraft(auth()->user(), [
                'store_id' => $this->newStoreId,
                'operational_supplier_id' => $this->newSupplierId,
                'currency' => $this->newCurrency,
                'tax_treatment' => $this->newTaxTreatment,
            ]);
        } catch (ValidationException $exception) {
            $map = ['store_id' => 'newStoreId', 'operational_supplier_id' => 'newSupplierId', 'currency' => 'newCurrency', 'tax_treatment' => 'newTaxTreatment'];

            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(fn ($messages, $key) => [$map[$key] ?? 'newStoreId' => $messages])->all());
        }

        $this->redirectRoute('subdomain.purchases.edit', $purchase, navigate: true);
    }

    #[Layout('layouts.backend')]
    public function render(): View
    {
        $this->authorize('viewAny', Purchase::class);

        $stores = Store::query()->accessibleTo(auth()->user())->orderBy('code')->get(['id', 'code', 'name']);
        $storeIds = $stores->pluck('id')->all();
        $storeFilter = in_array((int) $this->store, $storeIds, true) ? (int) $this->store : null;
        $view = in_array($this->view, self::VIEWS, true) ? $this->view : 'all';
        $term = Str::trim($this->search);
        $pattern = '%'.addcslashes($term, '\\%_').'%';

        $purchases = Purchase::query()
            ->whereIn('store_id', $storeFilter !== null ? [$storeFilter] : $storeIds)
            ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $matches) => $matches
                ->where('supplier_invoice_number', 'like', $pattern)
                ->orWhereHas('supplier', fn (Builder $supplier) => $supplier->where('name', 'like', $pattern))))
            ->when($view !== 'all', fn (Builder $query) => match ($view) {
                'draft' => $query->where('status', PurchaseStatus::DRAFT),
                'awaiting_receipt' => $query->where('status', PurchaseStatus::POSTED)->whereNull('received_at'),
                'outstanding' => $query->where('status', PurchaseStatus::POSTED)->whereColumn('paid_total', '<', 'total'),
                'settled' => $query->where('status', PurchaseStatus::POSTED)->whereColumn('paid_total', '=', 'total'),
                'reversed' => $query->where('status', PurchaseStatus::REVERSED),
                'cancelled' => $query->where('status', PurchaseStatus::CANCELLED),
            })
            ->with(['supplier:id,name', 'store:id,code'])
            ->select(['id', 'store_id', 'operational_supplier_id', 'currency', 'status', 'supplier_invoice_number', 'invoice_date', 'total', 'paid_total', 'received_at', 'correction_of_purchase_id'])
            ->orderByDesc('id')
            ->paginate(25);

        $canCreate = Gate::allows('create', Purchase::class);

        return view('livewire.backend.purchases.purchases-index', [
            'purchases' => $purchases,
            'stores' => $stores,
            'suppliers' => $canCreate ? OperationalSupplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']) : collect(),
            'currencies' => Currency::cases(),
            'taxTreatments' => PurchaseTaxTreatment::cases(),
            'views' => self::VIEWS,
            'canCreate' => $canCreate,
            'isFiltering' => $term !== '' || $view !== 'all' || $storeFilter !== null,
            ...$this->localeViewData(),
        ])->title(__('purchases.title'));
    }
}
