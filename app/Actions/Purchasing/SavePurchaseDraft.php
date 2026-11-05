<?php

declare(strict_types=1);

namespace App\Actions\Purchasing;

use App\Enums\Currency;
use App\Enums\PurchaseStatus;
use App\Enums\PurchaseTaxTreatment;
use App\Exceptions\Purchasing\InvalidAmountException;
use App\Models\ProductDefinition;
use App\Models\Purchase;
use App\Models\PurchaseLine;
use App\Models\Store;
use App\Models\User;
use App\Services\Purchasing\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SavePurchaseDraft
{
    public const MAX_LINE_QUANTITY = 10_000;

    /** Each tracked unit needs its own identifiers at receipt, so device lines stay small. */
    public const MAX_TRACKED_UNITS_PER_LINE = 100;

    /**
     * Replace a draft's header and lines when it is still at the revision the editor loaded.
     *
     * Totals are always recalculated here from quantities and unit prices.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $actor, Purchase $purchase, int $expectedRevision, array $input): Purchase
    {
        Gate::forUser($actor)->authorize('update', $purchase);

        $validated = Validator::make($input, [
            'store_id' => ['required', 'integer', Rule::exists('stores', 'id')],
            'operational_supplier_id' => ['required', 'integer', Rule::exists('operational_suppliers', 'id')->where('is_active', true)],
            'currency' => ['required', Rule::enum(Currency::class)],
            'tax_treatment' => ['required', Rule::enum(PurchaseTaxTreatment::class)],
            'supplier_invoice_number' => ['nullable', 'string', 'max:64'],
            'invoice_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'due_date' => ['nullable', 'date_format:Y-m-d', Rule::when(filled($input['invoice_date'] ?? null), 'after_or_equal:invoice_date')],
            'additional_charges' => ['nullable', 'string', 'max:32'],
            'additional_charges_note' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['array', 'max:'.Money::MAX_LINES],
            'lines.*.product_definition_id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:'.self::MAX_LINE_QUANTITY],
            'lines.*.unit_cost' => ['required', 'string', 'max:32'],
        ], PurchaseAttributes::messages(), PurchaseAttributes::header())->validate();

        Gate::forUser($actor)->authorize('createIn', [Purchase::class, Store::query()->findOrFail($validated['store_id'])]);

        $input = new PurchaseInput;
        $charges = $input->amount($validated['additional_charges'] ?? null, 'additional_charges', __('purchases.fields.additional_charges'), allowZero: true);

        $lines = array_values($validated['lines'] ?? []);
        $definitions = ProductDefinition::query()
            ->whereKey(array_map(fn (array $line): int => (int) $line['product_definition_id'], $lines))
            ->get(['id', 'name', 'model', 'sku', 'tracking_mode', 'is_active'])
            ->keyBy('id');

        $rows = [];

        foreach ($lines as $index => $line) {
            $definition = $definitions->get((int) $line['product_definition_id']);

            if ($definition === null || ! $definition->is_active) {
                $input->add("lines.{$index}.product_definition_id", __('purchases.errors.product_unavailable'));

                continue;
            }

            $quantity = (int) $line['quantity'];

            if ($definition->tracking_mode->tracksIndividualItems() && $quantity > self::MAX_TRACKED_UNITS_PER_LINE) {
                $input->add("lines.{$index}.quantity", __('purchases.errors.tracked_quantity_max', ['max' => self::MAX_TRACKED_UNITS_PER_LINE]));
            }

            $unitCost = $input->amount($line['unit_cost'], "lines.{$index}.unit_cost", __('purchases.fields.unit_cost'), Money::MAX_UNIT_AMOUNT);

            if ($unitCost === null) {
                continue;
            }

            try {
                $lineTotal = Money::multiply($quantity, $unitCost);
            } catch (InvalidAmountException $exception) {
                $input->add("lines.{$index}.unit_cost", __('purchases.errors.line_total_too_large', ['max' => Money::format(Money::MAX_TOTAL_AMOUNT)]));

                continue;
            }

            $rows[] = [
                'line_number' => $index + 1,
                'product_definition_id' => $definition->id,
                'tracking_mode' => $definition->tracking_mode->value,
                'product_name' => $definition->name,
                'product_model' => $definition->model,
                'product_sku' => $definition->sku,
                'quantity' => $quantity,
                'unit_cost' => Money::toDecimal($unitCost),
                'line_total' => Money::toDecimal($lineTotal),
                'line_total_minor' => $lineTotal,
            ];
        }

        $input->throwIfInvalid();

        try {
            $itemsTotal = Money::sum(array_column($rows, 'line_total_minor'));
            $total = Money::sum([$itemsTotal, (int) $charges]);
        } catch (InvalidAmountException) {
            throw ValidationException::withMessages(['lines' => __('purchases.errors.invoice_total_too_large', ['max' => Money::format(Money::MAX_TOTAL_AMOUNT)])]);
        }

        $invoiceNumber = Str::trim((string) ($validated['supplier_invoice_number'] ?? ''));

        return DB::transaction(function () use ($purchase, $expectedRevision, $validated, $rows, $charges, $itemsTotal, $total, $invoiceNumber): Purchase {
            $locked = Purchase::query()->whereKey($purchase->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== PurchaseStatus::DRAFT) {
                throw ValidationException::withMessages(['revision' => __('purchases.errors.not_draft')]);
            }

            if ($locked->revision !== $expectedRevision) {
                throw ValidationException::withMessages(['revision' => __('purchases.errors.stale_draft')]);
            }

            PurchaseLine::query()->where('purchase_id', $locked->id)->delete();

            $now = now();
            PurchaseLine::query()->insert(array_map(fn (array $row): array => [
                ...array_diff_key($row, ['line_total_minor' => true]),
                'purchase_id' => $locked->id,
                'created_at' => $now,
                'updated_at' => $now,
            ], $rows));

            $locked->update([
                'store_id' => $validated['store_id'],
                'operational_supplier_id' => $validated['operational_supplier_id'],
                'currency' => $validated['currency'],
                'tax_treatment' => $validated['tax_treatment'],
                'supplier_invoice_number' => $invoiceNumber === '' ? null : $invoiceNumber,
                'invoice_date' => $validated['invoice_date'] ?? null,
                'due_date' => $validated['due_date'] ?? null,
                'additional_charges' => Money::toDecimal((int) $charges),
                'additional_charges_note' => Str::trim((string) ($validated['additional_charges_note'] ?? '')) ?: null,
                'notes' => Str::trim((string) ($validated['notes'] ?? '')) ?: null,
                'items_total' => Money::toDecimal($itemsTotal),
                'total' => Money::toDecimal($total),
                'revision' => $locked->revision + 1,
            ]);

            return $locked;
        });
    }
}
