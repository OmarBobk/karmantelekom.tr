<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CatalogBarcodeSequence;
use App\Models\ProductDefinition;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Support\InteractsWithOperationalInventory;
use Tests\TestCase;

/**
 * Competing internal-barcode writers under real MariaDB locks.
 */
class InternalBarcodeConcurrencyTest extends TestCase
{
    use DatabaseTruncation;
    use InteractsWithOperationalInventory;

    private User $owner;

    private User $rival;

    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Concurrency tests need the MySQL/MariaDB test database.');
        }

        $this->setUpOperationalRoles();
        $this->owner = $this->createOwner(['name' => 'Omar']);
        $this->rival = $this->createOwner(['name' => 'Rival']);
        Store::factory()->create(['code' => 'KT01']);

        // DatabaseTruncation clears the seeded singleton; recreate it before locking.
        CatalogBarcodeSequence::query()->insertOrIgnore([
            'id' => CatalogBarcodeSequence::SINGLETON_ID,
            'last_number' => 200000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        $this->truncateTablesForAllConnections();

        parent::tearDown();
    }

    #[Test]
    public function two_blank_creates_receive_distinct_internal_barcodes(): void
    {
        $results = $this->raceWhileSequenceLocked([
            $this->createRequest($this->owner, 'Cable A', (string) Str::uuid()),
            $this->createRequest($this->rival, 'Cable B', (string) Str::uuid()),
        ]);

        $this->assertSame(
            ['ok', 'ok'],
            array_column($results, 'status'),
            json_encode($results, JSON_THROW_ON_ERROR),
        );
        $barcodes = array_column($results, 'barcode');
        sort($barcodes);
        $this->assertSame(['200001', '200002'], $barcodes);
        $this->assertCount(2, array_unique(array_column($results, 'definition_id')));
        $this->assertSame(200002, (int) CatalogBarcodeSequence::query()->value('last_number'));
    }

    #[Test]
    public function manual_in_band_sku_and_automatic_barcode_serialize_without_cross_column_ambiguity(): void
    {
        $results = $this->raceWhileSequenceLocked([
            $this->createRequest($this->owner, 'Auto cable', (string) Str::uuid()),
            $this->createRequest(
                $this->rival,
                'Manual SKU cable',
                (string) Str::uuid(),
                barcode: '8690000000999',
                sku: '200001',
            ),
        ]);

        foreach ($results as $result) {
            $this->assertContains($result['status'], ['ok', 'validation'], json_encode($results, JSON_THROW_ON_ERROR));
        }

        $okCount = count(array_filter($results, fn (array $row): bool => $row['status'] === 'ok'));
        $this->assertGreaterThanOrEqual(1, $okCount, json_encode($results, JSON_THROW_ON_ERROR));
        $this->assertSame(2, count($results));
        $this->assertNoCrossColumnAmbiguity();
    }

    #[Test]
    public function overlapping_same_key_creates_with_in_band_sku_replay_one_product(): void
    {
        $key = (string) Str::uuid();

        $results = $this->raceWhileSequenceLocked([
            $this->createRequest($this->owner, 'Shared cable', $key, sku: '200001'),
            $this->createRequest($this->owner, 'Shared cable', $key, sku: '200001'),
        ]);

        $this->assertSame(
            ['ok', 'ok'],
            array_column($results, 'status'),
            json_encode($results, JSON_THROW_ON_ERROR),
        );
        $this->assertSame($results[0]['definition_id'], $results[1]['definition_id']);
        $this->assertSame($results[0]['barcode'], $results[1]['barcode']);
        $this->assertMatchesRegularExpression('/^20\d{4}$/', (string) $results[0]['barcode']);
        $this->assertSame(1, ProductDefinition::query()->count());
        $this->assertSame(1, ProductDefinition::query()->where('sku', '200001')->count());
        $product = ProductDefinition::query()->sole();
        $this->assertSame($product->id, $results[0]['definition_id']);
        $this->assertSame($product->barcode, $results[0]['barcode']);
    }

    private function assertNoCrossColumnAmbiguity(): void
    {
        $rows = ProductDefinition::query()->get(['id', 'sku', 'barcode']);
        $skuToId = [];
        $barcodeToId = [];

        foreach ($rows as $row) {
            if ($row->sku !== null) {
                $skuToId[$row->sku] = $row->id;
            }
            if ($row->barcode !== null) {
                $barcodeToId[$row->barcode] = $row->id;
            }
        }

        foreach ($skuToId as $value => $skuOwnerId) {
            if (isset($barcodeToId[$value]) && $barcodeToId[$value] !== $skuOwnerId) {
                $this->fail("Cross-column ambiguity for {$value}: sku on #{$skuOwnerId}, barcode on #{$barcodeToId[$value]}");
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $requests
     * @return list<array<string, mixed>>
     */
    private function raceWhileSequenceLocked(array $requests): array
    {
        $processes = [];

        DB::beginTransaction();

        try {
            CatalogBarcodeSequence::query()->whereKey(1)->lockForUpdate()->firstOrFail();

            foreach ($requests as $request) {
                $processes[] = $this->startWorker($request);
            }

            $this->waitForBlockedWorkers(count($requests));
        } finally {
            DB::commit();
        }

        return array_map(fn (array $process) => $this->finishWorker($process), $processes);
    }

    /**
     * @return array<string, mixed>
     */
    private function createRequest(
        User $actor,
        string $name,
        string $requestKey,
        ?string $barcode = null,
        ?string $sku = null,
    ): array {
        return [
            'operation' => 'create_definition',
            'actor_id' => $actor->id,
            'store_id' => Store::query()->value('id'),
            'name' => $name,
            'request_key' => $requestKey,
            'barcode' => $barcode,
            'sku' => $sku,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{process: resource, pipes: array<int, resource>}
     */
    private function startWorker(array $payload): array
    {
        $environment = [
            ...getenv(),
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'mysql',
            'DB_DATABASE' => 'dev_store_testing',
        ];

        $process = proc_open(
            [PHP_BINARY, base_path('tests/Support/pos-worker.php'), json_encode($payload, JSON_THROW_ON_ERROR)],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            base_path(),
            $environment,
        );

        if (! is_resource($process)) {
            throw new RuntimeException('Could not start the catalog barcode worker.');
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
        $exitCode = proc_close($worker['process']);

        $this->assertSame(0, $exitCode, "Worker failed: {$errors}{$output}");

        return json_decode((string) $output, true, flags: JSON_THROW_ON_ERROR);
    }

    private function waitForBlockedWorkers(int $expected): void
    {
        $deadline = microtime(true) + 30;

        while (microtime(true) < $deadline) {
            if ((int) DB::scalar('select count(*) from information_schema.innodb_lock_waits') >= $expected) {
                return;
            }

            usleep(100_000);
        }

        throw new RuntimeException("Workers did not block on the held locks within 30 seconds (expected {$expected}).");
    }
}
