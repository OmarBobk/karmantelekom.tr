<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Events\ShopCreated;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ShopCreatedEventTest extends TestCase
{
    public function test_shop_created_event_contains_correct_data(): void
    {
        $user = new User(['id' => 1, 'name' => 'Test User']);
        $shop = new Shop(['id' => 1, 'name' => 'Test Shop']);

        $event = new ShopCreated($shop, $user->id);

        $this->assertEquals($shop->id, $event->shop->id);
        $this->assertEquals($user->id, $event->userId);
        $this->assertEquals('Test Shop', $event->shop->name);
    }

    public function test_shop_created_event_works_without_user_id(): void
    {
        $shop = new Shop(['id' => 1, 'name' => 'Test Shop']);

        $event = new ShopCreated($shop);

        $this->assertEquals($shop->id, $event->shop->id);
        $this->assertNull($event->userId);
    }

    public function test_shop_created_event_can_be_dispatched(): void
    {
        Event::fake();

        $shop = new Shop(['id' => 1, 'name' => 'Test Shop']);
        $user = new User(['id' => 1, 'name' => 'Test User']);

        ShopCreated::dispatch($shop, $user->id);

        Event::assertDispatched(ShopCreated::class, function ($event) use ($shop) {
            return $event->shop->id === $shop->id;
        });
    }
}
