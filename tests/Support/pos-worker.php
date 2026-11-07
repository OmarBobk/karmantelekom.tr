<?php

declare(strict_types=1);

/*
 * Test-only worker for SalesConcurrencyTest and CustomerPaymentConcurrencyTest: runs one counter,
 * customer payment, or inventory action in its own
 * process (own database connection) and prints the outcome as JSON.
 *
 * Usage: php tests/Support/pos-worker.php '<json payload>'
 */

use App\Actions\Catalog\SaveProductDefinition;
use App\Actions\Customers\RecordCustomerPayment;
use App\Actions\Customers\SaveCounterCustomer;
use App\Actions\Inventory\AdjustQuantity;
use App\Actions\Inventory\RecordInspection;
use App\Actions\Pos\FinalizeCheckout;
use App\Actions\Pos\RecordCardApproval;
use App\Actions\Pos\StartTerminalPayment;
use App\Enums\AdjustmentReason;
use App\Enums\InspectionOutcome;
use App\Enums\MovementType;
use App\Enums\SalePaymentMethod;
use App\Enums\UnitStatus;
use App\Exceptions\Inventory\IdempotencyConflictException;
use App\Exceptions\Inventory\StaleUnitStateException;
use App\Models\Customer;
use App\Models\ProductDefinition;
use App\Models\SalePayment;
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
$store = Store::query()->findOrFail($payload['store_id']);

if ($payload['fail_late'] ?? false) {
    SalePayment::creating(function (SalePayment $payment): void {
        if ($payment->method === SalePaymentMethod::CASH) {
            throw new RuntimeException('Simulated failure after the sale rows were written.');
        }
    });
}

try {
    $result = match ($payload['operation']) {
        'finalize' => (function () use ($actor, $store, $payload): array {
            $sale = app(FinalizeCheckout::class)($actor, $store, $payload['attempt_key'], $payload['revision'], $payload['cash'] ?? null);

            return ['status' => 'ok', 'sale_id' => $sale->id, 'number' => $sale->number];
        })(),
        'approve' => (function () use ($actor, $store, $payload): array {
            $approval = app(RecordCardApproval::class)($actor, $store, $payload['attempt_key'], $payload['revision'], $payload['payment_key'], $payload['terminal_id'], $payload['reference'], $payload['amount']);

            return ['status' => 'ok', 'approval_id' => $approval->id];
        })(),
        'start' => (function () use ($actor, $store, $payload): array {
            $start = app(StartTerminalPayment::class)($actor, $store, $payload['attempt_key'], $payload['revision'], $payload['request_key'], $payload['terminal_id'], $payload['amount']);

            return ['status' => 'ok', 'payment_key' => $start->paymentKey(), 'new_start' => $start->isNewStart];
        })(),
        'inspect' => (function () use ($actor, $store, $payload): array {
            app(RecordInspection::class)(
                $actor, $store, $payload['unit_id'], UnitStatus::from($payload['expected_status']), $payload['expected_revision'],
                InspectionOutcome::NEEDS_REPAIR, null, 'Concurrent inspection', $payload['key'],
            );

            return ['status' => 'ok'];
        })(),
        'decrease' => (function () use ($actor, $store, $payload): array {
            app(AdjustQuantity::class)(
                $actor, $store, ProductDefinition::query()->findOrFail($payload['definition_id']), MovementType::ADJUSTMENT_DECREASE,
                AdjustmentReason::DAMAGED, $payload['quantity'], 'Concurrent decrease', $payload['key'],
            );

            return ['status' => 'ok'];
        })(),
        'customer' => (function () use ($actor, $store, $payload): array {
            $customer = app(SaveCounterCustomer::class)($actor, $store, $payload['name'], $payload['phone']);

            return ['status' => 'ok', 'customer_id' => $customer->id, 'created' => $customer->wasRecentlyCreated];
        })(),
        'customer_payment' => (function () use ($actor, $store, $payload): array {
            $payment = app(RecordCustomerPayment::class)($actor, $store, Customer::query()->findOrFail($payload['customer_id']), $payload['input'], $payload['key']);

            return ['status' => 'ok', 'payment_id' => $payment->id, 'created' => $payment->wasRecentlyCreated];
        })(),
        'set_price' => (function () use ($actor, $payload): array {
            $definition = ProductDefinition::query()->findOrFail($payload['definition_id']);
            app(SaveProductDefinition::class)($actor, $definition, [
                'name' => $definition->name,
                'tracking_mode' => $definition->tracking_mode->value,
                'sku' => $definition->sku,
                'barcode' => $definition->barcode,
                'selling_price' => $payload['price'],
            ]);

            return ['status' => 'ok'];
        })(),
    };
} catch (ValidationException $exception) {
    $result = ['status' => 'validation', 'errors' => array_keys($exception->errors())];
} catch (IdempotencyConflictException) {
    $result = ['status' => 'conflict'];
} catch (StaleUnitStateException $exception) {
    $result = ['status' => 'stale', 'current_status' => $exception->currentStatus->value];
} catch (RuntimeException $exception) {
    $result = ['status' => 'error', 'message' => $exception->getMessage()];
}

echo json_encode($result, JSON_THROW_ON_ERROR);
