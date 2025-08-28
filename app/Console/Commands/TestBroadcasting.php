<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Events\OrderCreated;
use App\Events\OrderUpdated;
use App\Events\ShopAssigned;
use App\Events\ShopCreated;
use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * TestBroadcasting Command
 *
 * Test command to verify broadcasting functionality
 */
class TestBroadcasting extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:broadcasting {--event=all : Specific event to test (shop-created, shop-assigned, order-created, order-updated, all)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test broadcasting events to verify Reverb setup';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $event = $this->option('event');

        $this->info('Testing Laravel Reverb Broadcasting...');
        $this->newLine();

        try {
            switch ($event) {
                case 'shop-created':
                    $this->testShopCreated();
                    break;
                case 'shop-assigned':
                    $this->testShopAssigned();
                    break;
                case 'order-created':
                    $this->testOrderCreated();
                    break;
                case 'order-updated':
                    $this->testOrderUpdated();
                    break;
                case 'all':
                default:
                    $this->testAllEvents();
                    break;
            }

            $this->info('✅ Broadcasting test completed successfully!');
            $this->info('Check your browser console and Reverb server logs for event reception.');
            
            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Broadcasting test failed: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
            
            return self::FAILURE;
        }
    }

    /**
     * Test shop created event
     */
    private function testShopCreated(): void
    {
        $this->info('Testing ShopCreated event...');

        // Create a test shop
        $shop = Shop::factory()->create([
            'name' => 'Test Shop - ' . now()->format('H:i:s'),
            'phone' => '+90 555 123 4567',
            'address' => 'Test Address, Istanbul',
        ]);

        // Dispatch the event
        event(new ShopCreated($shop, auth()->id()));

        $this->info("✅ ShopCreated event dispatched for shop: {$shop->name}");
    }

    /**
     * Test shop assigned event
     */
    private function testShopAssigned(): void
    {
        $this->info('Testing ShopAssigned event...');

        // Get or create test shop and salesperson
        $shop = Shop::first() ?? Shop::factory()->create();
        $salesperson = User::first() ?? User::factory()->create();

        // Dispatch the event
        event(new ShopAssigned($shop, $salesperson, auth()->user()));

        $this->info("✅ ShopAssigned event dispatched for shop: {$shop->name} -> {$salesperson->name}");
    }

    /**
     * Test order created event
     */
    private function testOrderCreated(): void
    {
        $this->info('Testing OrderCreated event...');

        // Create a test order
        $order = Order::factory()->create([
            'total_price' => 150.00,
            'notes' => 'Test order created at ' . now()->format('H:i:s'),
        ]);

        // Dispatch the event
        event(new OrderCreated($order, auth()->id()));

        $this->info("✅ OrderCreated event dispatched for order: #{$order->id}");
    }

    /**
     * Test order updated event
     */
    private function testOrderUpdated(): void
    {
        $this->info('Testing OrderUpdated event...');

        // Get or create test order
        $order = Order::first() ?? Order::factory()->create();
        
        // Store original data
        $originalData = [
            'status' => $order->status,
            'total_price' => $order->total_price,
            'shop_id' => $order->shop_id,
            'notes' => $order->notes,
        ];

        // Update the order
        $order->update([
            'total_price' => $order->total_price + 50,
            'notes' => 'Updated at ' . now()->format('H:i:s'),
        ]);

        // Dispatch the event
        event(new OrderUpdated($order, $originalData, auth()->id()));

        $this->info("✅ OrderUpdated event dispatched for order: #{$order->id}");
    }

    /**
     * Test all events
     */
    private function testAllEvents(): void
    {
        $this->info('Testing all broadcasting events...');
        $this->newLine();

        $this->testShopCreated();
        $this->newLine();

        $this->testShopAssigned();
        $this->newLine();

        $this->testOrderCreated();
        $this->newLine();

        $this->testOrderUpdated();
        $this->newLine();

        $this->info('🎉 All events tested successfully!');
    }
}
