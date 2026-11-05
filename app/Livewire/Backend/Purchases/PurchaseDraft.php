<?php

declare(strict_types=1);

namespace App\Livewire\Backend\Purchases;

use App\Actions\Purchasing\CancelPurchaseDraft;
use App\Actions\Purchasing\PostPurchase;
use App\Actions\Purchasing\SavePurchaseDraft;
use App\Enums\Currency;
use App\Enums\PurchaseTaxTreatment;
use App\Exceptions\Purchasing\InvalidAmountException;
use App\Livewire\Backend\Concerns\UsesOperationalLocale;
use App\Models\OperationalSupplier;
use App\Models\ProductDefinition;
use App\Models\Purchase;
use App\Models\PurchaseLine;
use App\Models\Store;
use App\Services\Purchasing\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Edit a draft supplier invoice, then review and post the saved revision.
 *
 * The form is the single source of truth until saved; the saved revision is what posts.
 */
class PurchaseDraft extends Component
{
    use UsesOperationalLocale;

    private const FIELD_MAP = [
        'store_id' => 'storeId',
        'operational_supplier_id' => 'supplierId',
        'currency' => 'currency',
        'tax_treatment' => 'taxTreatment',
        'supplier_invoice_number' => 'invoiceNumber',
        'invoice_date' => 'invoiceDate',
        'due_date' => 'dueDate',
        'additional_charges' => 'additionalCharges',
        'additional_charges_note' => 'chargesNote',
        'notes' => 'notes',
    ];

    #[Locked]
    public int $purchaseId;

    #[Locked]
    public int $revision;

    public string $storeId = '';

    public string $supplierId = '';

    public string $currency = '';

    public string $taxTreatment = '';

    public string $invoiceNumber = '';

    public string $invoiceDate = '';

    public string $dueDate = '';

    public string $additionalCharges = '';

    public string $chargesNote = '';

    public string $notes = '';

    /** @var list<array{product_definition_id: int, quantity: string, unit_cost: string}> */
    public array $lines = [];

    public string $productSearch = '';

    public function mount(Purchase $purchase): void
    {
        $this->authorize('update', $purchase);

        $this->purchaseId = $purchase->id;
        $this->fillFrom($purchase);

        if (! $purchase->isDraft()) {
            $this->redirectRoute('subdomain.purchases.show', $purchase, navigate: true);

            return;
        }

        if ($notification = session('purchases.notify')) {
            $this->dispatch('notify', $notification);
        }
    }

    public function addProduct(int $definitionId): void
    {
        $this->authorize('update', $this->purchase());

        $definition = ProductDefinition::query()->active()->find($definitionId, ['id']);

        if ($definition === null) {
            $this->addError('productSearch', __('purchases.errors.product_unavailable'));

            return;
        }

        $this->appendLine($definition->id);
    }

    /**
     * Enter in the product box adds the product with exactly this SKU or barcode.
     */
    public function addExactMatch(): void
    {
        $this->authorize('update', $this->purchase());

        $identifier = ProductDefinition::normalizeIdentifier($this->productSearch);

        $definition = $identifier === null ? null : ProductDefinition::query()
            ->active()
            ->where(fn (Builder $matches) => $matches->where('sku', $identifier)->orWhere('barcode', $identifier))
            ->first(['id']);

        if ($definition === null) {
            $this->addError('productSearch', __('purchases.draft.exact_not_found'));

            return;
        }

        $this->appendLine($definition->id);
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
        $this->resetErrorBag();
    }

    public function save(SavePurchaseDraft $savePurchaseDraft): bool
    {
        $purchase = $this->purchase();
        $this->authorize('update', $purchase);
        $this->resetErrorBag();

        try {
            $saved = $savePurchaseDraft(auth()->user(), $purchase, $this->revision, [
                'store_id' => $this->storeId,
                'operational_supplier_id' => $this->supplierId,
                'currency' => $this->currency,
                'tax_treatment' => $this->taxTreatment,
                'supplier_invoice_number' => $this->invoiceNumber,
                'invoice_date' => $this->invoiceDate !== '' ? $this->invoiceDate : null,
                'due_date' => $this->dueDate !== '' ? $this->dueDate : null,
                'additional_charges' => $this->additionalCharges,
                'additional_charges_note' => $this->chargesNote,
                'notes' => $this->notes,
                'lines' => $this->lines,
            ]);
        } catch (ValidationException $exception) {
            throw $this->mapErrors($exception);
        }

        $this->fillFrom($saved);
        $this->dispatch('notify', ['type' => 'success', 'message' => __('purchases.draft.saved')]);

        return true;
    }

    /**
     * Save the form, then open the confirmation showing the saved revision's server totals.
     */
    public function reviewPosting(SavePurchaseDraft $savePurchaseDraft): void
    {
        if ($this->save($savePurchaseDraft)) {
            $this->dispatch('open-dialog', id: 'post-review');
        }
    }

    public function post(PostPurchase $postPurchase): void
    {
        $purchase = $this->purchase();
        $this->authorize('update', $purchase);

        try {
            $result = $postPurchase(auth()->user(), $purchase, $this->revision);
        } catch (ValidationException $exception) {
            $this->dispatch('close-dialog', id: 'post-review');

            throw $this->mapErrors($exception);
        }

        $posted = $result->model;

        session()->flash('purchases.notify', [
            'type' => $result->applied ? 'success' : 'info',
            'message' => $result->applied
                ? __('purchases.post.posted', ['total' => Money::format($posted->totalMinor()).' '.$posted->currency->value])
                : __('purchases.post.already_posted'),
        ]);

        $this->redirectRoute('subdomain.purchases.show', $posted, navigate: true);
    }

    public function cancelDraft(CancelPurchaseDraft $cancelPurchaseDraft): void
    {
        $purchase = $this->purchase();
        $this->authorize('update', $purchase);

        try {
            $cancelPurchaseDraft(auth()->user(), $purchase);
        } catch (ValidationException $exception) {
            throw $this->mapErrors($exception);
        }

        session()->flash('purchases.notify', ['type' => 'success', 'message' => __('purchases.draft.cancelled')]);
        $this->redirectRoute('subdomain.purchases.show', $purchase, navigate: true);
    }

    #[Layout('layouts.backend')]
    public function render(): View
    {
        $purchase = $this->purchase()->load(['supplier:id,name,is_active', 'correctionOf:id,supplier_invoice_number']);
        $this->authorize('update', $purchase);

        $definitions = ProductDefinition::query()
            ->whereKey(array_column($this->lines, 'product_definition_id'))
            ->get(['id', 'name', 'model', 'sku', 'tracking_mode', 'is_active'])
            ->keyBy('id');

        $suppliers = OperationalSupplier::query()
            ->where(fn (Builder $query) => $query->where('is_active', true)->orWhere('id', $purchase->operational_supplier_id))
            ->orderBy('name')
            ->get(['id', 'name', 'is_active']);

        return view('livewire.backend.purchases.purchase-draft', [
            'purchase' => $purchase,
            'definitions' => $definitions,
            'productMatches' => $this->productMatches(),
            'lineTotals' => $this->previewLineTotals(),
            'previewTotal' => $this->previewTotal(),
            'stores' => Store::query()->accessibleTo(auth()->user())->orderBy('code')->get(['id', 'code', 'name']),
            'suppliers' => $suppliers,
            'currencies' => Currency::cases(),
            'taxTreatments' => PurchaseTaxTreatment::cases(),
            'currencyCode' => Currency::tryFrom($this->currency)?->value ?? $purchase->currency->value,
            ...$this->localeViewData(),
        ])->title(__('purchases.draft.title'));
    }

    private function purchase(): Purchase
    {
        return Purchase::query()->findOrFail($this->purchaseId);
    }

    private function appendLine(int $definitionId): void
    {
        $this->lines[] = ['product_definition_id' => $definitionId, 'quantity' => '1', 'unit_cost' => ''];
        $this->productSearch = '';
        $this->resetErrorBag('productSearch');
        $this->dispatch('purchase-line-added', index: count($this->lines) - 1);
    }

    private function fillFrom(Purchase $purchase): void
    {
        $this->revision = $purchase->revision;
        $this->storeId = (string) $purchase->store_id;
        $this->supplierId = (string) $purchase->operational_supplier_id;
        $this->currency = $purchase->currency->value;
        $this->taxTreatment = $purchase->tax_treatment->value;
        $this->invoiceNumber = (string) $purchase->supplier_invoice_number;
        $this->invoiceDate = (string) $purchase->invoice_date?->toDateString();
        $this->dueDate = (string) $purchase->due_date?->toDateString();
        $this->additionalCharges = $purchase->additionalChargesMinor() > 0 ? Money::input($purchase->additionalChargesMinor()) : '';
        $this->chargesNote = (string) $purchase->additional_charges_note;
        $this->notes = (string) $purchase->notes;
        $this->lines = $purchase->lines()->get()->map(fn (PurchaseLine $line): array => [
            'product_definition_id' => $line->product_definition_id,
            'quantity' => (string) $line->quantity,
            'unit_cost' => Money::input($line->unitCostMinor()),
        ])->all();
    }

    /**
     * @return Collection<int, ProductDefinition>
     */
    private function productMatches(): Collection
    {
        $term = ProductDefinition::normalizeIdentifier($this->productSearch);

        if ($term === null || mb_strlen($term) < 2) {
            return new Collection;
        }

        return ProductDefinition::query()
            ->active()
            ->where(fn (Builder $matches) => $matches->search($term)->orWhere('sku', $term)->orWhere('barcode', $term))
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'model', 'sku', 'tracking_mode']);
    }

    /**
     * Display-only line totals for the current form values; null where input is not yet valid.
     *
     * @return array<int, int|null>
     */
    private function previewLineTotals(): array
    {
        return array_map(function (array $line): ?int {
            try {
                return Money::multiply(max(0, (int) $line['quantity']), Money::parse((string) $line['unit_cost'], Money::MAX_UNIT_AMOUNT));
            } catch (InvalidAmountException) {
                return null;
            }
        }, $this->lines);
    }

    private function previewTotal(): ?int
    {
        $totals = $this->previewLineTotals();

        if (in_array(null, $totals, true)) {
            return null;
        }

        try {
            return Money::sum([...$totals, Money::parse($this->additionalCharges, allowZero: true) ?: 0]);
        } catch (InvalidAmountException) {
            return trim($this->additionalCharges) === '' ? Money::sum($totals) : null;
        }
    }

    private function mapErrors(ValidationException $exception): ValidationException
    {
        $messages = [];

        foreach ($exception->errors() as $key => $keyMessages) {
            $messages[self::FIELD_MAP[$key] ?? $key] = $keyMessages;
        }

        return ValidationException::withMessages($messages);
    }
}
