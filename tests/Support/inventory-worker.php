<?php

declare(strict_types=1);

/*
 * Test-only worker for InventoryConcurrencyTest: runs one inventory action in its own
 * process (own database connection) and prints the outcome as JSON.
 *
 * Usage: php tests/Support/inventory-worker.php '<json payload>'
 */

use App\Actions\Catalog\SaveProductDefinition;
use App\Actions\Inventory\CorrectUnitIdentifier;
use App\Actions\Inventory\RecordOpeningBalance;
use App\Actions\Inventory\RecordStockCount;
use App\Actions\Inventory\RegisterUnit;
use App\Actions\Inventory\StartRepair;
use App\Enums\RepairerType;
use App\Enums\UnitCondition;
use App\Enums\UnitStatus;
use App\Exceptions\Inventory\IdempotencyConflictException;
use App\Exceptions\Inventory\StaleUnitStateException;
use App\Models\InventoryMovement;
use App\Models\ProductDefinition;
use App\Models\Store;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! app()->environment('testing') || config('database.connections.'.config('database.default').'.database') !== 'dev_store_testing') {
    fwrite(STDERR, "Refusing to run outside the isolated testing database.\n");
    exit(2);
}

$payload = json_decode($argv[1] ?? '{}', true, flags: JSON_THROW_ON_ERROR);
$actor = User::query()->findOrFail($payload['actor_id']);
$store = Store::query()->find($payload['store_id'] ?? null);
$definition = ProductDefinition::query()->findOrFail($payload['definition_id']);

$movementResult = fn (InventoryMovement $movement): array => [
    'status' => 'ok',
    'movement_id' => $movement->id,
    'created' => $movement->wasRecentlyCreated,
];

try {
    $result = match ($payload['operation']) {
        'opening' => $movementResult(app(RecordOpeningBalance::class)(
            $actor, $store, $definition, $payload['quantity'], null, $payload['key'],
        )),
        'count' => (function () use ($actor, $store, $definition, $payload, $movementResult): array {
            $movement = app(RecordStockCount::class)(
                $actor, $store, $definition, $payload['counted'], $payload['count_started_after'], 'Concurrent count', $payload['key'],
            );

            return $movement === null ? ['status' => 'unchanged'] : $movementResult($movement);
        })(),
        'register' => $movementResult(app(RegisterUnit::class)(
            $actor, $store, $definition, UnitCondition::B, $payload['imei'], null, null, null, $payload['key'],
        )),
        'start_repair' => $movementResult(app(StartRepair::class)(
            $actor, $store, $payload['unit_id'], UnitStatus::from($payload['expected_status']), $payload['expected_revision'],
            RepairerType::EXTERNAL, $payload['repairer'], 'Concurrent repair', $payload['key'],
        )),
        'correct_identifier' => $movementResult(app(CorrectUnitIdentifier::class)(
            $actor, $store, $payload['unit_id'], $payload['identifier_id'], $payload['expected_value'], $payload['new_value'], 'Concurrent correction', $payload['key'],
        )),
        'change_tracking_mode' => (function () use ($actor, $definition, $payload): array {
            app(SaveProductDefinition::class)($actor, $definition, ['name' => $definition->name, 'tracking_mode' => $payload['tracking_mode']]);

            return ['status' => 'ok'];
        })(),
    };
} catch (ValidationException $exception) {
    $result = ['status' => 'validation', 'errors' => array_keys($exception->errors())];
} catch (IdempotencyConflictException) {
    $result = ['status' => 'conflict'];
} catch (StaleUnitStateException $exception) {
    $result = ['status' => 'stale', 'current_status' => $exception->currentStatus->value];
}

echo json_encode($result, JSON_THROW_ON_ERROR);
