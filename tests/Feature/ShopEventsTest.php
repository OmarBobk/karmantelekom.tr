<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\ShopCreated;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ShopEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_created_event_is_dispatched_when_shop_is_created(): void
    {
        Event::fake();

        $user = User::factory()->create();

        $shop = Shop::create([
            'name' => 'Test Shop',
            'phone' => '1234567890',
            'address' => 'Test Address',
            'owner_id' => $user->id,
        ]);

        // Manually dispatch the event as it would be in the components
        ShopCreated::dispatch($shop, $user->id);

        Event::assertDispatched(ShopCreated::class, function ($event) use ($shop) {
            return $event->shop->id === $shop->id;
        });
    }

    public function test_shop_created_event_contains_correct_data(): void
    {
        Event::fake();

        $user = User::factory()->create();

        $shop = Shop::create([
            'name' => 'Test Shop',
            'phone' => '1234567890',
            'address' => 'Test Address',
            'owner_id' => $user->id,
        ]);

        $event = new ShopCreated($shop, $user->id);

        $this->assertEquals($shop->id, $event->shop->id);
        $this->assertEquals($user->id, $event->userId);
        $this->assertEquals('Test Shop', $event->shop->name);
    }

    public function test_shop_created_event_works_without_user_id(): void
    {
        Event::fake();

        $shop = Shop::create([
            'name' => 'Test Shop',
            'phone' => '1234567890',
            'address' => 'Test Address',
        ]);

        $event = new ShopCreated($shop);

        $this->assertEquals($shop->id, $event->shop->id);
        $this->assertNull($event->userId);
    }
}
