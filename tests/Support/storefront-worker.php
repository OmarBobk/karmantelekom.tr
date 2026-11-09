<?php

declare(strict_types=1);

/*
 * Test-only worker for the storefront concurrency tests: places one cart order in its own
 * process (own database connection) and prints the outcome as JSON.
 *
 * Usage: php tests/Support/storefront-worker.php '<json payload>'
 */

use App\Actions\Storefront\PlaceCartOrder;
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

try {
    $order = app(PlaceCartOrder::class)(User::query()->findOrFail($payload['buyer_id']), $payload['shop_id'], null);
    $result = ['status' => 'ok', 'order_id' => $order->id];
} catch (ValidationException $exception) {
    $result = ['status' => 'refused', 'errors' => $exception->errors()];
}

echo json_encode($result, JSON_THROW_ON_ERROR);
