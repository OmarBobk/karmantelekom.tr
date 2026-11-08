<?php

declare(strict_types=1);

/*
 * Test-only worker for PurchasingConcurrencyTest: runs one purchasing action in its own
 * process (own database connection) and prints the outcome as JSON.
 *
 * Usage: php tests/Support/purchasing-worker.php '<json payload>'
 */

use App\Actions\Cashbox\StartCashboxTransfer;
use App\Actions\Catalog\SaveProductDefinition;
use App\Actions\Inventory\RegisterUnit;
use App\Actions\Purchasing\PostPurchase;
use App\Actions\Purchasing\ReceivePurchase;
use App\Actions\Purchasing\RecordSupplierPayment;
use App\Enums\UnitCondition;
use App\Exceptions\Inventory\IdempotencyConflictException;
use App\Exceptions\Purchasing\PurchaseAlreadyReceivedException;
use App\Models\ProductDefinition;
use App\Models\Purchase;
use App\Models\Store;
use App\Models\SupplierCashPayout;
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
$purchase = isset($payload['purchase_id']) ? Purchase::query()->findOrFail($payload['purchase_id']) : null;

try {
    $result = match ($payload['operation']) {
        'receive' => (function () use ($actor, $purchase, $payload): array {
            $receipt = app(ReceivePurchase::class)($actor, $purchase, $payload['units'], null, $payload['key']);

            return ['status' => 'ok', 'receipt_id' => $receipt->id, 'created' => $receipt->wasRecentlyCreated];
        })(),
        'pay' => (function () use ($actor, $purchase, $payload): array {
            $payment = app(RecordSupplierPayment::class)($actor, $purchase, $payload['input'], $payload['key'], $payload['session_id'] ?? null);

            return ['status' => 'ok', 'payment_id' => $payment->id, 'kind' => $payment instanceof SupplierCashPayout ? 'payout' : 'payment', 'created' => $payment->wasRecentlyCreated];
        })(),
        'start_transfer' => (function () use ($actor, $payload): array {
            $transfer = app(StartCashboxTransfer::class)($actor, Store::query()->findOrFail($payload['store_id']), $payload['session_id'], $payload['input'], $payload['key']);

            return ['status' => 'ok', 'transfer_id' => $transfer->id, 'created' => $transfer->wasRecentlyCreated];
        })(),
        'post' => (function () use ($actor, $purchase, $payload): array {
            $result = app(PostPurchase::class)($actor, $purchase, $payload['revision']);

            return ['status' => 'ok', 'applied' => $result->applied];
        })(),
        'register' => (function () use ($actor, $payload): array {
            $movement = app(RegisterUnit::class)(
                $actor,
                Store::query()->findOrFail($payload['store_id']),
                ProductDefinition::query()->findOrFail($payload['definition_id']),
                UnitCondition::B,
                $payload['imei'],
                null,
                null,
                null,
                $payload['key'],
            );

            return ['status' => 'ok', 'movement_id' => $movement->id];
        })(),
        'change_tracking_mode' => (function () use ($actor, $payload): array {
            $definition = ProductDefinition::query()->findOrFail($payload['definition_id']);
            app(SaveProductDefinition::class)($actor, $definition, ['name' => $definition->name, 'tracking_mode' => $payload['tracking_mode']]);

            return ['status' => 'ok'];
        })(),
    };
} catch (ValidationException $exception) {
    $result = ['status' => 'validation', 'errors' => array_keys($exception->errors())];
} catch (IdempotencyConflictException) {
    $result = ['status' => 'conflict'];
} catch (PurchaseAlreadyReceivedException) {
    $result = ['status' => 'already_received'];
}

echo json_encode($result, JSON_THROW_ON_ERROR);
