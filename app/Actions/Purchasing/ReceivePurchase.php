<?php

declare(strict_types=1);

namespace App\Actions\Purchasing;

use App\Actions\Inventory\InventoryLedger;
use App\Actions\Inventory\UnitIdentity;
use App\Enums\AdjustmentReason;
use App\Enums\MovementType;
use App\Enums\PurchaseStatus;
use App\Enums\UnitCondition;
use App\Enums\UnitStatus;
use App\Exceptions\Inventory\IdempotencyConflictException;
use App\Exceptions\Purchasing\PurchaseAlreadyReceivedException;
use App\Models\ProductDefinition;
use App\Models\Purchase;
use App\Models\PurchaseLine;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptLine;
use App\Models\PurchaseReceiptUnit;
use App\Models\Store;
use App\Models\User;
use App\Services\Idempotency;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ReceivePurchase
{
    public function __construct(private readonly InventoryLedger $inventoryLedger) {}

    /**
     * Receive every line of a posted invoice into its store in one transaction.
     *
     * Quantity lines add to the store balance; tracked lines register one unit per
     * invoiced item with the identifiers and condition entered here. Every movement
     * references its receipt line and every unit records its acquisition price.
     *
     * Lock order: purchase, then line definitions (ascending), then balances.
     *
     * @param  array<int|string, list<array{condition?: ?string, imei?: ?string, secondary_imei?: ?string, serial?: ?string}>>  $units  Keyed by purchase line id.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     * @throws IdempotencyConflictException when the key was used for a different submission.
     * @throws PurchaseAlreadyReceivedException when another submission already received the invoice.
     */
    public function __invoke(User $actor, Purchase $purchase, array $units, ?string $note, string $idempotencyKey): PurchaseReceipt
    {
        Gate::forUser($actor)->authorize('receive', $purchase);

        $key = Idempotency::key($idempotencyKey);
        $note = InventoryLedger::prepareNote($note);
        $units = self::normalizeUnits($units);

        $fingerprint = Idempotency::fingerprint([
            'operation' => 'purchase_receipt',
            'purchase_id' => $purchase->id,
            'note' => $note,
            'units' => $units,
        ]);

        try {
            return DB::transaction(function () use ($actor, $purchase, $units, $note, $key, $fingerprint): PurchaseReceipt {
                $locked = Purchase::query()->whereKey($purchase->id)->lockForUpdate()->firstOrFail();

                $existing = PurchaseReceipt::query()->where('idempotency_key', $key)->first();

                if ($existing !== null) {
                    Idempotency::assertReplay($existing->user_id, $existing->request_fingerprint, $actor, $fingerprint);

                    return $existing;
                }

                if ($locked->received_at !== null || $locked->receipt()->exists()) {
                    throw new PurchaseAlreadyReceivedException;
                }

                if ($locked->status !== PurchaseStatus::POSTED) {
                    throw ValidationException::withMessages(['receipt' => __('purchases.errors.receive_requires_posted')]);
                }

                $lines = $locked->lines()->get();
                $definitions = $this->inventoryLedger->lockDefinitions($lines->pluck('product_definition_id')->all());

                $this->validate($lines, $definitions, $units);

                $store = Store::query()->findOrFail($locked->store_id);
                $now = now();

                $receipt = PurchaseReceipt::query()->create([
                    'purchase_id' => $locked->id,
                    'store_id' => $store->id,
                    'user_id' => $actor->id,
                    'note' => $note,
                    'idempotency_key' => $key,
                    'request_fingerprint' => $fingerprint,
                    'received_at' => $now,
                ]);

                $ordered = $lines->sortBy([['product_definition_id', 'asc'], ['line_number', 'asc']]);

                foreach ($ordered as $line) {
                    $definition = $definitions->get($line->product_definition_id);

                    $receiptLine = PurchaseReceiptLine::query()->create([
                        'purchase_receipt_id' => $receipt->id,
                        'purchase_line_id' => $line->id,
                        'product_definition_id' => $definition->id,
                        'quantity' => $line->quantity,
                        'unit_cost' => $line->unit_cost,
                    ]);

                    $movement = [
                        'type' => MovementType::PURCHASE_RECEIPT,
                        'reason' => AdjustmentReason::PURCHASE,
                        'note' => $note,
                        'reference_type' => PurchaseReceiptLine::MORPH_ALIAS,
                        'reference_id' => $receiptLine->id,
                        'occurred_at' => $now,
                    ];

                    if (! $definition->tracking_mode->tracksIndividualItems()) {
                        $balance = $this->inventoryLedger->lockBalance($store, $definition);
                        $balance->increment('quantity', $line->quantity);

                        $this->inventoryLedger->apply($actor, $store, $definition, "receipt:{$receipt->id}:{$receiptLine->id}", $fingerprint, [
                            ...$movement,
                            'quantity_change' => $line->quantity,
                            'quantity_after' => $balance->quantity,
                        ]);

                        continue;
                    }

                    foreach ($units[$line->id] as $position => $entry) {
                        $unit = UnitIdentity::create(
                            $store,
                            $definition,
                            UnitCondition::from($entry['condition']),
                            $entry['imei'],
                            $entry['secondary_imei'],
                            $entry['serial'],
                        );

                        PurchaseReceiptUnit::query()->create([
                            'purchase_receipt_line_id' => $receiptLine->id,
                            'inventory_unit_id' => $unit->id,
                            'currency' => $locked->currency,
                            'unit_cost' => $line->unit_cost,
                        ]);

                        $this->inventoryLedger->apply($actor, $store, $definition, "receipt:{$receipt->id}:{$receiptLine->id}:".($position + 1), $fingerprint, [
                            ...$movement,
                            'inventory_unit_id' => $unit->id,
                            'quantity_change' => 1,
                            'unit_status_after' => UnitStatus::AVAILABLE,
                        ]);
                    }
                }

                $locked->update(['received_at' => $now]);

                return $receipt;
            });
        } catch (UniqueConstraintViolationException $exception) {
            // Everything above rolled back. Decide what the committed winner means for this request.
            $existing = PurchaseReceipt::query()->where('idempotency_key', $key)->first();

            if ($existing !== null) {
                Idempotency::assertReplay($existing->user_id, $existing->request_fingerprint, $actor, $fingerprint);

                return $existing;
            }

            if (PurchaseReceipt::query()->where('purchase_id', $purchase->id)->exists()) {
                throw new PurchaseAlreadyReceivedException;
            }

            $this->throwTakenIdentifiers($purchase->lines()->get(), $units);

            throw $exception;
        }
    }

    /**
     * Trim identifiers and drop blank ones so equivalent submissions share a fingerprint.
     *
     * @param  array<int|string, mixed>  $units
     * @return array<int, list<array{condition: ?string, imei: ?string, secondary_imei: ?string, serial: ?string}>>
     */
    private static function normalizeUnits(array $units): array
    {
        $normalized = [];

        foreach ($units as $lineId => $entries) {
            if (! is_array($entries)) {
                continue;
            }

            foreach (array_values($entries) as $entry) {
                $entry = is_array($entry) ? $entry : [];
                $normalized[(int) $lineId][] = [
                    'condition' => UnitIdentity::normalize(is_string($entry['condition'] ?? null) ? $entry['condition'] : null),
                    'imei' => UnitIdentity::normalize(is_string($entry['imei'] ?? null) ? $entry['imei'] : null),
                    'secondary_imei' => UnitIdentity::normalize(is_string($entry['secondary_imei'] ?? null) ? $entry['secondary_imei'] : null),
                    'serial' => UnitIdentity::normalize(is_string($entry['serial'] ?? null) ? $entry['serial'] : null),
                ];
            }
        }

        ksort($normalized);

        return $normalized;
    }

    /**
     * Check posted tracking snapshots, unit counts, conditions, and identifiers before any stock write.
     *
     * @param  Collection<int, PurchaseLine>  $lines
     * @param  Collection<int, ProductDefinition>  $definitions
     * @param  array<int, list<array{condition: ?string, imei: ?string, secondary_imei: ?string, serial: ?string}>>  $units
     *
     * @throws ValidationException
     */
    private function validate(Collection $lines, Collection $definitions, array $units): void
    {
        $errors = [];
        $submittedImeis = [];
        $submittedSerials = [];

        foreach ($lines as $line) {
            $definition = $definitions->get($line->product_definition_id);

            if ($definition === null || $definition->tracking_mode !== $line->tracking_mode) {
                $errors["lines.{$line->id}"] = __('purchases.errors.tracking_mode_changed', ['line' => $line->line_number]);

                continue;
            }

            if (! $line->tracking_mode->tracksIndividualItems()) {
                continue;
            }

            $entries = $units[$line->id] ?? [];

            if (count($entries) !== $line->quantity) {
                $errors["lines.{$line->id}"] = __('purchases.errors.unit_count', ['line' => $line->line_number, 'count' => $line->quantity]);

                continue;
            }

            foreach ($entries as $index => $entry) {
                $prefix = "units.{$line->id}.{$index}";
                $condition = UnitCondition::tryFrom((string) $entry['condition']);

                $unitErrors = $condition === null
                    ? ['condition' => __('inventory.errors.condition_not_allowed')]
                    : UnitIdentity::modeErrors($definition, $condition);
                $unitErrors += UnitIdentity::formatErrors($entry['imei'], $entry['secondary_imei'], $entry['serial']);

                foreach (['imei', 'secondary_imei'] as $field) {
                    $value = $entry[$field];

                    if ($value !== null && ! isset($unitErrors[$field])) {
                        if (isset($submittedImeis[$value])) {
                            $unitErrors[$field] = __('purchases.errors.duplicate_in_receipt');
                        }

                        $submittedImeis[$value] = "{$prefix}.{$field}";
                    }
                }

                if ($entry['serial'] !== null && ! isset($unitErrors['serial'])) {
                    $serialKey = $definition->id.':'.$entry['serial'];

                    if (isset($submittedSerials[$serialKey])) {
                        $unitErrors['serial'] = __('purchases.errors.duplicate_in_receipt');
                    }

                    $submittedSerials[$serialKey] = "{$prefix}.serial";
                }

                foreach ($unitErrors as $field => $message) {
                    $errors["{$prefix}.{$field}"] = $message;
                }
            }
        }

        if ($errors === []) {
            $errors = $this->takenIdentifierErrors($submittedImeis, $submittedSerials);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<string, string>  $imeis  Value => error key.
     * @param  array<string, string>  $serials  "definitionId:value" => error key.
     * @return array<string, string>
     */
    private function takenIdentifierErrors(array $imeis, array $serials): array
    {
        $errors = [];

        foreach (UnitIdentity::takenImeis(array_map('strval', array_keys($imeis))) as $taken) {
            $errors[$imeis[$taken]] = __('inventory.errors.imei_taken');
        }

        $serialsByDefinition = [];

        foreach (array_keys($serials) as $serialKey) {
            [$definitionId, $value] = explode(':', (string) $serialKey, 2);
            $serialsByDefinition[(int) $definitionId][] = $value;
        }

        foreach ($serialsByDefinition as $definitionId => $values) {
            foreach (UnitIdentity::takenSerials($definitionId, $values) as $taken) {
                $errors[$serials[$definitionId.':'.$taken]] = __('inventory.errors.serial_taken');
            }
        }

        return $errors;
    }

    /**
     * After a rolled-back identifier collision, report which entered identifiers are now taken.
     *
     * @param  Collection<int, PurchaseLine>  $lines
     * @param  array<int, list<array{condition: ?string, imei: ?string, secondary_imei: ?string, serial: ?string}>>  $units
     *
     * @throws ValidationException
     */
    private function throwTakenIdentifiers(Collection $lines, array $units): void
    {
        $imeis = [];
        $serials = [];

        foreach ($lines as $line) {
            foreach ($units[$line->id] ?? [] as $index => $entry) {
                foreach (['imei', 'secondary_imei'] as $field) {
                    if ($entry[$field] !== null) {
                        $imeis[$entry[$field]] = "units.{$line->id}.{$index}.{$field}";
                    }
                }

                if ($entry['serial'] !== null) {
                    $serials[$line->product_definition_id.':'.$entry['serial']] = "units.{$line->id}.{$index}.serial";
                }
            }
        }

        $errors = $this->takenIdentifierErrors($imeis, $serials);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
