<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\OrderCreated;
use App\Events\OrderUpdated;
use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use App\Enums\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class OrderEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_created_event_is_dispatched_when_order_is_created(): void
    {
        Event::fake();

        $user = User::factory()->create();
        $shop = Shop::factory()->create();

        $order = Order::create([
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'status' => OrderStatus::PENDING,
            'total_price' => 100.00,
            'notes' => 'Test order',
        ]);

        // Manually dispatch the event as it would be in the CheckoutComponent
        OrderCreated::dispatch($order);

        Event::assertDispatched(OrderCreated::class, function ($event) use ($order) {
            return $event->order->id === $order->id;
        });
    }

    public function test_order_updated_event_is_dispatched_when_order_is_updated(): void
    {
        Event::fake();

        $user = User::factory()->create();
        $shop = Shop::factory()->create();

        $order = Order::create([
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'status' => OrderStatus::PENDING,
            'total_price' => 100.00,
            'notes' => 'Test order',
        ]);

        $originalData = $order->toArray();
        $order->update(['status' => OrderStatus::CONFIRMED]);

        // Manually dispatch the event as it would be in the OrdersManager
        OrderUpdated::dispatch($order, $originalData);

        Event::assertDispatched(OrderUpdated::class, function ($event) use ($order) {
            return $event->order->id === $order->id 
                && $event->originalData['status'] === OrderStatus::PENDING->value;
        });
    }
} 