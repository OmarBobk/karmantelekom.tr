<?php

declare(strict_types=1);

namespace App\Actions\Purchasing;

use App\Enums\PurchaseStatus;
use App\Models\ProductDefinition;
use App\Models\Purchase;
use App\Models\PurchaseLine;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CopyPurchaseAsCorrection
{
    /**
     * Start a correction draft from a reversed invoice. The draft may reuse the reversed invoice number.
     *
     * An open correction draft of the same invoice is returned instead of creating another.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $actor, Purchase $source): TransitionResult
    {
        Gate::forUser($actor)->authorize('update', $source);

        return DB::transaction(function () use ($actor, $source): TransitionResult {
            $locked = Purchase::query()->whereKey($source->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== PurchaseStatus::REVERSED) {
                throw ValidationException::withMessages(['reason' => __('purchases.errors.copy_requires_reversed')]);
            }

            $openDraft = $locked->corrections()->where('status', PurchaseStatus::DRAFT)->first();

            if ($openDraft !== null) {
                return new TransitionResult($openDraft, false);
            }

            $draft = Purchase::query()->create([
                'store_id' => $locked->store_id,
                'operational_supplier_id' => $locked->operational_supplier_id,
                'currency' => $locked->currency,
                'tax_treatment' => $locked->tax_treatment,
                'status' => PurchaseStatus::DRAFT,
                'supplier_invoice_number' => $locked->supplier_invoice_number,
                'invoice_date' => $locked->invoice_date,
                'due_date' => $locked->due_date,
                'items_total' => $locked->items_total,
                'additional_charges' => $locked->additional_charges,
                'additional_charges_note' => $locked->additional_charges_note,
                'total' => $locked->total,
                'notes' => $locked->notes,
                'revision' => 1,
                'correction_of_purchase_id' => $locked->id,
                'root_purchase_id' => $locked->rootId(),
                'created_by' => $actor->id,
            ]);

            $lines = $locked->lines()->get();
            $definitions = ProductDefinition::query()->whereKey($lines->pluck('product_definition_id'))->get(['id', 'tracking_mode'])->keyBy('id');
            $now = now();

            PurchaseLine::query()->insert($lines->map(fn (PurchaseLine $line): array => [
                'purchase_id' => $draft->id,
                'line_number' => $line->line_number,
                'product_definition_id' => $line->product_definition_id,
                'tracking_mode' => ($definitions->get($line->product_definition_id)?->tracking_mode ?? $line->tracking_mode)->value,
                'product_name' => $line->product_name,
                'product_model' => $line->product_model,
                'product_sku' => $line->product_sku,
                'quantity' => $line->quantity,
                'unit_cost' => $line->unit_cost,
                'line_total' => $line->line_total,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());

            return new TransitionResult($draft, true);
        });
    }
}
