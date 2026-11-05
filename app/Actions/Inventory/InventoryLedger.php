<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Exceptions\Inventory\IdempotencyConflictException;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\ProductDefinition;
use App\Models\Store;
use App\Models\User;
use App\Services\Idempotency;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Writes every stock change together with its append-only movement.
 *
 * Lock order: product definition rows first (ascending id), then balance or unit rows,
 * then a unit's open repair, cost entry, or identifier rows. Tracking-mode edits take the
 * same definition lock, so mode and history checks made inside the mutation cannot race them.
 */
class InventoryLedger
{
    public const MAX_NOTE_LENGTH = 1000;

    /**
     * Run a stock mutation once per idempotency key, in its own transaction.
     *
     * The mutation receives the locked definition and returns the movement's
     * type, reason, and change attributes. An identical retry by the same actor
     * returns the original movement without running the mutation again.
     *
     * Detail rows that must reference the movement (inspections, repairs, costs, identifier
     * corrections) are written by $afterMovement inside the same transaction.
     *
     * @param  array<string, mixed>  $payload  Normalized request values that identify the submission.
     * @param  Closure(ProductDefinition): array<string, mixed>  $mutation
     * @param  (Closure(InventoryMovement): void)|null  $afterMovement
     *
     * @throws IdempotencyConflictException
     * @throws UniqueConstraintViolationException when a non-key unique constraint is violated.
     */
    public function record(
        User $actor,
        Store $store,
        int $productDefinitionId,
        string $operation,
        ?int $unitId,
        string $idempotencyKey,
        array $payload,
        Closure $mutation,
        ?Closure $afterMovement = null,
    ): InventoryMovement {
        $key = Idempotency::key($idempotencyKey);

        $fingerprint = Idempotency::fingerprint([
            'operation' => $operation,
            'store_id' => $store->id,
            'product_definition_id' => $productDefinitionId,
            'unit_id' => $unitId,
            'payload' => $payload,
        ]);

        try {
            return DB::transaction(function () use ($actor, $store, $productDefinitionId, $key, $fingerprint, $mutation, $afterMovement): InventoryMovement {
                $definition = ProductDefinition::query()
                    ->whereKey($productDefinitionId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $existing = InventoryMovement::query()->where('idempotency_key', $key)->first();

                if ($existing !== null) {
                    return $this->replay($existing, $actor, $fingerprint);
                }

                $movement = $this->apply($actor, $store, $definition, $key, $fingerprint, $mutation($definition));

                if ($afterMovement !== null) {
                    $afterMovement($movement);
                }

                return $movement;
            });
        } catch (UniqueConstraintViolationException $exception) {
            // The losing transaction has rolled back; resolve against the committed winner.
            $existing = InventoryMovement::query()->where('idempotency_key', $key)->first();

            if ($existing === null) {
                throw $exception;
            }

            return $this->replay($existing, $actor, $fingerprint);
        }
    }

    /**
     * Lock product definitions in ascending id order, keyed by id.
     * Callers that write stock for several definitions must lock them all here first.
     *
     * @param  list<int>  $definitionIds
     * @return Collection<int, ProductDefinition>
     */
    public function lockDefinitions(array $definitionIds): Collection
    {
        $this->assertInTransaction();

        $ids = array_values(array_unique(array_map('intval', $definitionIds)));
        sort($ids);

        return ProductDefinition::query()
            ->whereKey($ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }

    /**
     * Write one movement inside the caller's transaction. The caller already holds the
     * definition lock and has applied the balance or unit change the attributes describe.
     *
     * @param  array<string, mixed>  $attributes  Type, reason, change, and optional reference attributes.
     */
    public function apply(
        User $actor,
        Store $store,
        ProductDefinition $lockedDefinition,
        string $idempotencyKey,
        string $fingerprint,
        array $attributes,
    ): InventoryMovement {
        $this->assertInTransaction();

        return InventoryMovement::query()->create([
            ...$attributes,
            'store_id' => $store->id,
            'product_definition_id' => $lockedDefinition->id,
            'user_id' => $actor->id,
            'idempotency_key' => Idempotency::key($idempotencyKey),
            'request_fingerprint' => $fingerprint,
            'occurred_at' => $attributes['occurred_at'] ?? now(),
        ]);
    }

    /**
     * Lock (creating at zero if missing) the store's balance row for a definition.
     * Call only from inside a transaction, after the definition lock is held.
     */
    public function lockBalance(Store $store, ProductDefinition $definition): InventoryBalance
    {
        InventoryBalance::query()->insertOrIgnore([
            'store_id' => $store->id,
            'product_definition_id' => $definition->id,
            'quantity' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return InventoryBalance::query()
            ->where('store_id', $store->id)
            ->where('product_definition_id', $definition->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * Trim a movement note, treat blank notes as absent, and validate it.
     *
     * @throws ValidationException
     */
    public static function prepareNote(?string $note, bool $required = false): ?string
    {
        $note = Str::trim((string) $note);

        if ($required && $note === '') {
            throw ValidationException::withMessages(['note' => __('inventory.errors.note_required')]);
        }

        if (mb_strlen($note) > self::MAX_NOTE_LENGTH) {
            throw ValidationException::withMessages([
                'note' => __('operations.validation.max', ['attribute' => __('inventory.fields.note'), 'max' => self::MAX_NOTE_LENGTH]),
            ]);
        }

        return $note === '' ? null : $note;
    }

    private function assertInTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Inventory writes must run inside a database transaction.');
        }
    }

    /**
     * @throws IdempotencyConflictException
     */
    private function replay(InventoryMovement $existing, User $actor, string $fingerprint): InventoryMovement
    {
        Idempotency::assertReplay($existing->user_id, $existing->request_fingerprint, $actor, $fingerprint);

        return $existing;
    }
}
