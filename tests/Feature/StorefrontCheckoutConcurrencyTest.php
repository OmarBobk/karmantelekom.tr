<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Inventory\RecordOpeningBalance;
use App\Actions\Storefront\LinkStorefrontProduct;
use App\Actions\Storefront\SetStorefrontQuantity;
use App\Enums\TrackingMode;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductDefinition;
use App\Models\Shop;
use App\Models\Store;
use App\Models\StorefrontListing;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Support\InteractsWithOperationalInventory;
use Tests\TestCase;

/**
 * Checkout and linking from independent PHP processes, each with its own database connection.
 *
 * The test process links the product inside an open transaction, so it holds the product row
 * lock, starts a checkout worker, waits until the database reports the worker blocked, then
 * commits the link.
 */
class StorefrontCheckoutConcurrencyTest extends TestCase
{
    use DatabaseTruncation;
    use InteractsWithOperationalInventory;

    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Concurrency tests need the MySQL/MariaDB test database.');
        }
    }

    protected function tearDown(): void
    {
        $this->truncateTablesForAllConnections();

        parent::tearDown();
    }

    #[Test]
    public function a_checkout_waiting_on_a_link_that_commits_first_writes_no_order(): void
    {
        $this->setUpOperationalRoles();
        $owner = $this->createOwner();
        $store = Store::factory()->create(['code' => 'KT01']);
        $cable = ProductDefinition::factory()->priced('49.90')->create(['tracking_mode' => TrackingMode::QUANTITY]);
        app(RecordOpeningBalance::class)($owner, $store, $cable, 10, null, (string) Str::uuid());
        app(SetStorefrontQuantity::class)($owner, $store, $cable->id, 4);

        $product = Product::factory()->create(['is_active' => true, 'category_id' => Category::factory()->create()->id]);
        $buyer = User::factory()->create();
        Cart::query()->create(['user_id' => $buyer->id])->items()->create(['product_id' => $product->id, 'price' => 20, 'quantity' => 1, 'subtotal' => 20]);
        $shop = Shop::factory()->create();

        DB::beginTransaction();

        try {
            app(LinkStorefrontProduct::class)($owner, $cable->id, $product->id);
            $worker = $this->startWorker(['buyer_id' => $buyer->id, 'shop_id' => $shop->id]);
            $this->waitForBlockedWorker();
        } finally {
            DB::commit();
        }

        $result = $this->finishWorker($worker);

        $this->assertSame('refused', $result['status']);
        $this->assertSame([__('main.storefront_display_only', [], app()->getLocale())], $result['errors']['cart']);
        $this->assertSame(1, StorefrontListing::count());
        $this->assertSame(0, Order::count());
        $this->assertSame(0, OrderItem::count());
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{process: resource, pipes: array<int, resource>}
     */
    private function startWorker(array $payload): array
    {
        $process = proc_open(
            [PHP_BINARY, base_path('tests/Support/storefront-worker.php'), json_encode($payload, JSON_THROW_ON_ERROR)],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            base_path(),
            [...getenv(), 'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'dev_store_testing'],
        );

        if (! is_resource($process)) {
            throw new RuntimeException('Could not start the storefront worker process.');
        }

        return ['process' => $process, 'pipes' => $pipes];
    }

    /**
     * @param  array{process: resource, pipes: array<int, resource>}  $worker
     * @return array<string, mixed>
     */
    private function finishWorker(array $worker): array
    {
        $output = stream_get_contents($worker['pipes'][1]);
        $errors = stream_get_contents($worker['pipes'][2]);
        fclose($worker['pipes'][1]);
        fclose($worker['pipes'][2]);

        $this->assertSame(0, proc_close($worker['process']), "Worker failed: {$errors}{$output}");

        return json_decode((string) $output, true, flags: JSON_THROW_ON_ERROR);
    }

    private function waitForBlockedWorker(): void
    {
        $deadline = microtime(true) + 30;

        while (microtime(true) < $deadline) {
            if ((int) DB::scalar('select count(*) from information_schema.innodb_lock_waits') >= 1) {
                return;
            }

            usleep(100_000);
        }

        throw new RuntimeException('The checkout worker did not block on the product lock within 30 seconds.');
    }
}
