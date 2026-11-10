<?php

declare(strict_types=1);

/*
 * Test-only worker for the branch order concurrency tests: confirms an order, starts a counter
 * terminal payment, or approves a Store Network reservation in its own process (own database
 * connection) and prints the outcome as JSON.
 *
 * Usage: php tests/Support/branch-order-worker.php '<json payload>'
 */

use App\Actions\Network\ApproveReservation;
use App\Actions\Pos\StartTerminalPayment;
use App\Actions\Storefront\ConfirmBranchOrder;
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
config(['storefront.branch_orders' => true]);
app()->setLocale('en');

try {
    $result = match ($payload['operation']) {
        'confirm' => (function () use ($actor, $store, $payload): array {
            $order = app(ConfirmBranchOrder::class)($actor, $store, $payload['order_id'], $payload['devices'], $payload['hours']);

            return ['status' => 'ok', 'order_status' => $order->status->value];
        })(),
        'start' => (function () use ($actor, $store, $payload): array {
            $start = app(StartTerminalPayment::class)($actor, $store, $payload['attempt_key'], $payload['revision'], $payload['request_key'], $payload['terminal_id'], $payload['amount']);

            return ['status' => 'ok', 'payment_key' => $start->paymentKey()];
        })(),
        'approve_reservation' => (function () use ($actor, $store, $payload): array {
            $reservation = app(ApproveReservation::class)($actor, $store, $payload['reservation_id']);

            return ['status' => 'ok', 'reservation_status' => $reservation->status->value];
        })(),
    };
} catch (ValidationException $exception) {
    $result = ['status' => 'validation', 'errors' => $exception->errors()];
}

echo json_encode($result, JSON_THROW_ON_ERROR);
