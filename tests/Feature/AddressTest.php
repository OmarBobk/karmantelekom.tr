<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class AddressTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function test_can_create_address(): void
    {
        $shop = Shop::factory()->create();
        
        $address = Address::factory()->create([
            'shop_id' => $shop->id,
            'label' => 'Head Office',
            'address_line' => '123 Main Street',
            'city' => 'New York',
            'is_primary' => true,
        ]);

        $this->assertDatabaseHas('addresses', [
            'id' => $address->id,
            'shop_id' => $shop->id,
            'label' => 'Head Office',
            'is_primary' => true,
        ]);
    }

    public function test_shop_has_addresses_relationship(): void
    {
        $shop = Shop::factory()->create();
        $addresses = Address::factory()->count(3)->create([
            'shop_id' => $shop->id,
        ]);

        $this->assertCount(3, $shop->addresses);
    }

    public function test_can_get_primary_address(): void
    {
        $shop = Shop::factory()->create();
        
        // Create non-primary addresses
        Address::factory()->count(2)->create([
            'shop_id' => $shop->id,
            'is_primary' => false,
        ]);

        // Create primary address
        $primaryAddress = Address::factory()->create([
            'shop_id' => $shop->id,
            'is_primary' => true,
        ]);

        $this->assertEquals($primaryAddress->id, $shop->primaryAddress->id);
    }

    public function test_only_one_primary_address_per_shop(): void
    {
        $shop = Shop::factory()->create();
        
        // Create first primary address
        $firstPrimary = Address::factory()->create([
            'shop_id' => $shop->id,
            'is_primary' => true,
        ]);

        // Create second primary address
        $secondPrimary = Address::factory()->create([
            'shop_id' => $shop->id,
            'is_primary' => true,
        ]);

        // Refresh models to get updated data
        $firstPrimary->refresh();
        $secondPrimary->refresh();

        // First address should no longer be primary
        $this->assertFalse($firstPrimary->is_primary);
        // Second address should be primary
        $this->assertTrue($secondPrimary->is_primary);
    }

    public function test_full_address_attribute(): void
    {
        $address = Address::factory()->create([
            'address_line' => '123 Main Street',
            'city' => 'New York',
            'state' => 'NY',
            'postal_code' => '10001',
        ]);

        $expected = '123 Main Street, New York, NY, 10001';
        $this->assertEquals($expected, $address->full_address);
    }

    public function test_coordinates_attribute(): void
    {
        $address = Address::factory()->create([
            'latitude' => 40.7128,
            'longitude' => -74.0060,
        ]);

        $coordinates = $address->coordinates;
        
        $this->assertIsArray($coordinates);
        $this->assertEquals(40.7128, $coordinates['lat']);
        $this->assertEquals(-74.0060, $coordinates['lng']);
    }

    public function test_set_as_primary_method(): void
    {
        $shop = Shop::factory()->create();
        
        $address1 = Address::factory()->create([
            'shop_id' => $shop->id,
            'is_primary' => true,
        ]);

        $address2 = Address::factory()->create([
            'shop_id' => $shop->id,
            'is_primary' => false,
        ]);

        $address2->setAsPrimary();

        $address1->refresh();
        $address2->refresh();

        $this->assertFalse($address1->is_primary);
        $this->assertTrue($address2->is_primary);
    }
}
