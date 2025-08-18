<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Livewire\Backend\Partials\NotificationDropdown;
use Illuminate\Notifications\DatabaseNotification;
use Tests\TestCase;

class NotificationDropdownTest extends TestCase
{
    public function test_extract_action_identifies_shop_creation(): void
    {
        $component = new NotificationDropdown();
        
        $description = "New shop 'Test Shop' has been created by John Doe";
        $action = $this->invokePrivateMethod($component, 'extractAction', [$description]);
        
        $this->assertEquals('created a new shop', $action);
    }

    public function test_extract_action_identifies_order_creation(): void
    {
        $component = new NotificationDropdown();
        
        $description = "Order #123 has been created with status 'pending' for 100.00 TL";
        $action = $this->invokePrivateMethod($component, 'extractAction', [$description]);
        
        $this->assertEquals('created an order', $action);
    }

    public function test_extract_action_identifies_generic_creation(): void
    {
        $component = new NotificationDropdown();
        
        $description = "Some item has been created by System";
        $action = $this->invokePrivateMethod($component, 'extractAction', [$description]);
        
        $this->assertEquals('created an item', $action);
    }

    public function test_get_shop_id_from_notification_data(): void
    {
        $component = new NotificationDropdown();
        
        $notification = new \stdClass();
        $notification->data = [
            'model_id' => 456,
            'model_type' => 'App\\Models\\Shop',
            'shop_name' => 'Test Shop'
        ];
        
        $shopId = $this->invokePrivateMethod($component, 'getShopId', [$notification]);
        
        $this->assertEquals(456, $shopId);
    }

    public function test_get_order_link_for_shop_notification(): void
    {
        $component = new NotificationDropdown();
        
        $notification = new \stdClass();
        $notification->data = [
            'model_type' => 'App\\Models\\Shop',
            'shop_name' => 'Test Shop'
        ];
        
        $link = $this->invokePrivateMethod($component, 'getOrderLink', [$notification]);
        
        $this->assertStringContainsString('shops', $link);
    }

    /**
     * Helper method to invoke private methods for testing
     */
    private function invokePrivateMethod($object, string $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $parameters);
    }
}
