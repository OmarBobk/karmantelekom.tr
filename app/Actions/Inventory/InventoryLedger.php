<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Exceptions\Inventory\IdempotencyConflictException;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\ProductDefinition;
use App\Models\Store;
use App\Models\User;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Writes every stock change together with its append-only movement.
 *
 * Lock order: product definition row first, then the balance or unit row. Tracking-mode
 * edits take the same definition lock, so mode and history checks made inside the
 * mutation cannot race them.
 */
class InventoryLedger
{
    public const MAX_NOTE_LENGTH = 1000;

    /**
     * Run a stock mutation once per idempotency key.
     *
     * The mutation receives the locked definition and returns the movement's
     * type, reason, and change attributes. An identical retry by the same actor
     * returns the original movement without running the mutation again.
     *
     * @param  array<string, mixed>  $payload  Normalized request values that identify the submission.
     * @param  Closure(ProductDefinition): array<string, mixed>  $mutation
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
    ): InventoryMovement {
        $key = Str::trim($idempotencyKey);

        if ($key === '' || strlen($key) > 64) {
            throw new InvalidArgumentException('An idempotency key of 1 to 64 characters is required.');
        }

        $fingerprint = hash('sha256', (string) json_encode([
            'operation' => $operation,
            'store_id' => $store->id,
            'product_definition_id' => $productDefinitionId,
            'unit_id' => $unitId,
            'payload' => $payload,
        ]));

        try {
            return DB::transaction(function () use ($actor, $store, $productDefinitionId, $key, $fingerprint, $mutation): InventoryMovement {
                $definition = ProductDefinition::query()
                    ->whereKey($productDefinitionId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $existing = InventoryMovement::query()->where('idempotency_key', $key)->first();

                if ($existing !== null) {
                    return $this->replay($existing, $actor, $fingerprint);
                }

                return InventoryMovement::query()->create([
                    ...$mutation($definition),
                    'store_id' => $store->id,
                    'product_definition_id' => $definition->id,
                    'user_id' => $actor->id,
                    'idempotency_key' => $key,
                    'request_fingerprint' => $fingerprint,
                    'occurred_at' => now(),
                ]);
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
     * Lock (creating at zero if missing) the store's balance row for a definition.
     * Call only from inside a mutation, after the definition lock is held.
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

    /**
     * @throws IdempotencyConflictException
     */
    private function replay(InventoryMovement $existing, User $actor, string $fingerprint): InventoryMovement
    {
        if ((int) $existing->user_id !== (int) $actor->id || ! hash_equals($existing->request_fingerprint, $fingerprint)) {
            throw new IdempotencyConflictException;
        }

        return $existing;
    }
}
