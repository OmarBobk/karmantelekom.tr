<?php

declare(strict_types=1);

namespace App\Actions\Purchasing;

use App\Actions\Inventory\InventoryLedger;
use App\Enums\PurchaseStatus;
use App\Enums\SupplierLedgerEntryType;
use App\Exceptions\Purchasing\InvalidAmountException;
use App\Models\OperationalSupplier;
use App\Models\Purchase;
use App\Models\SupplierInvoiceNumber;
use App\Models\SupplierLedgerEntry;
use App\Models\User;
use App\Services\Purchasing\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PostPurchase
{
    public function __construct(private readonly InventoryLedger $inventoryLedger) {}

    /**
     * Post the draft revision the operator reviewed and record the supplier obligation once.
     *
     * Lock order: purchase, then line product definitions (ascending), shared with receiving
     * and tracking-mode edits. Retrying a post of the same revision by the same actor is a no-op.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $actor, Purchase $purchase, int $expectedRevision): TransitionResult
    {
        Gate::forUser($actor)->authorize('update', $purchase);

        try {
            return DB::transaction(function () use ($actor, $purchase, $expectedRevision): TransitionResult {
                $locked = Purchase::query()->whereKey($purchase->id)->lockForUpdate()->firstOrFail();

                if ($locked->status === PurchaseStatus::POSTED
                    && $locked->revision === $expectedRevision
                    && (int) $locked->getAttribute('posted_by') === (int) $actor->id) {
                    return new TransitionResult($locked, false);
                }

                if ($locked->status !== PurchaseStatus::DRAFT) {
                    throw ValidationException::withMessages(['revision' => __('purchases.errors.not_draft')]);
                }

                if ($locked->revision !== $expectedRevision) {
                    throw ValidationException::withMessages(['revision' => __('purchases.errors.stale_draft')]);
                }

                $errors = new PurchaseInput;
                $lines = $locked->lines()->get();
                $supplier = OperationalSupplier::query()->find($locked->operational_supplier_id);
                $invoiceNumberKey = Purchase::normalizeInvoiceNumber($locked->supplier_invoice_number);

                if ($invoiceNumberKey === null) {
                    $errors->add('supplier_invoice_number', __('purchases.errors.invoice_number_required'));
                }

                if ($locked->invoice_date === null) {
                    $errors->add('invoice_date', __('purchases.errors.invoice_date_required'));
                }

                if ($lines->isEmpty()) {
                    $errors->add('lines', __('purchases.errors.lines_required'));
                }

                if ($supplier === null || ! $supplier->is_active) {
                    $errors->add('operational_supplier_id', __('purchases.errors.supplier_inactive'));
                }

                $definitions = $this->inventoryLedger->lockDefinitions($lines->pluck('product_definition_id')->all());

                foreach ($lines as $line) {
                    $definition = $definitions->get($line->product_definition_id);

                    if ($definition === null || $definition->tracking_mode !== $line->tracking_mode) {
                        $errors->add('lines', __('purchases.errors.tracking_mode_changed', ['line' => $line->line_number]));
                    }
                }

                $errors->throwIfInvalid();

                try {
                    $itemsTotal = Money::sum($lines->map(fn ($line) => Money::multiply($line->quantity, $line->unitCostMinor())));
                    $total = Money::sum([$itemsTotal, $locked->additionalChargesMinor()]);
                } catch (InvalidAmountException) {
                    throw ValidationException::withMessages(['lines' => __('purchases.errors.invoice_total_too_large', ['max' => Money::format(Money::MAX_TOTAL_AMOUNT)])]);
                }

                $this->claimInvoiceNumber($locked, $invoiceNumberKey);

                foreach ($lines as $line) {
                    $definition = $definitions->get($line->product_definition_id);
                    $line->update([
                        'product_name' => $definition->name,
                        'product_model' => $definition->model,
                        'product_sku' => $definition->sku,
                        'line_total' => Money::toDecimal(Money::multiply($line->quantity, $line->unitCostMinor())),
                    ]);
                }

                $now = now();
                $locked->update([
                    'status' => PurchaseStatus::POSTED,
                    'invoice_number_key' => $invoiceNumberKey,
                    'items_total' => Money::toDecimal($itemsTotal),
                    'total' => Money::toDecimal($total),
                    'supplier_name' => $supplier->name,
                    'supplier_tax_number' => $supplier->tax_number,
                    'posted_by' => $actor->id,
                    'posted_at' => $now,
                ]);

                SupplierLedgerEntry::query()->create([
                    'operational_supplier_id' => $locked->operational_supplier_id,
                    'store_id' => $locked->store_id,
                    'purchase_id' => $locked->id,
                    'currency' => $locked->currency,
                    'type' => SupplierLedgerEntryType::PURCHASE_POSTED,
                    'amount' => Money::toDecimal($total),
                    'user_id' => $actor->id,
                    'occurred_at' => $now,
                ]);

                return new TransitionResult($locked, true);
            });
        } catch (UniqueConstraintViolationException) {
            // Another posting claimed the same supplier invoice number first; this one rolled back.
            throw ValidationException::withMessages(['supplier_invoice_number' => __('purchases.errors.invoice_number_taken')]);
        }
    }

    /**
     * A number belongs to the correction chain that first posted it; only that chain may reuse it.
     *
     * @throws ValidationException
     */
    private function claimInvoiceNumber(Purchase $purchase, string $numberKey): void
    {
        $claim = SupplierInvoiceNumber::query()
            ->where('operational_supplier_id', $purchase->operational_supplier_id)
            ->where('number_key', $numberKey)
            ->lockForUpdate()
            ->first();

        if ($claim !== null && (int) $claim->root_purchase_id !== $purchase->rootId()) {
            throw ValidationException::withMessages(['supplier_invoice_number' => __('purchases.errors.invoice_number_taken')]);
        }

        $postedWithNumber = Purchase::query()
            ->where('operational_supplier_id', $purchase->operational_supplier_id)
            ->where('invoice_number_key', $numberKey)
            ->where('status', PurchaseStatus::POSTED)
            ->exists();

        if ($postedWithNumber) {
            throw ValidationException::withMessages(['supplier_invoice_number' => __('purchases.errors.invoice_number_taken')]);
        }

        if ($claim === null) {
            SupplierInvoiceNumber::query()->create([
                'operational_supplier_id' => $purchase->operational_supplier_id,
                'number_key' => $numberKey,
                'root_purchase_id' => $purchase->rootId(),
            ]);
        }
    }
}
