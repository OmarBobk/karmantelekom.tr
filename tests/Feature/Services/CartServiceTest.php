<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartServiceTest extends TestCase
{
    use RefreshDatabase;

    private CartService $cartService;
    private User $user;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->cartService = new CartService();
        $this->user = User::factory()->create();
        $this->product = Product::factory()->create();
        
        // Create a price for the product
        ProductPrice::factory()->create([
            'product_id' => $this->product->id,
            'base_price' => 100.00,
        ]);
    }

    public function test_sync_cart_handles_duplicate_entries(): void
    {
        // First sync - should create the item
        $items = [
            ['product_id' => $this->product->id, 'quantity' => 2]
        ];
        
        $this->cartService->syncCart($this->user->id, $items);
        
        $cart = Cart::where('user_id', $this->user->id)->first();
        $this->assertEquals(1, $cart->items->count());
        $this->assertEquals(2, $cart->items->first()->quantity);
        
        // Second sync with same product - should update quantity (replace, not add)
        $this->cartService->syncCart($this->user->id, $items);
        
        $cart->refresh();
        $this->assertEquals(1, $cart->items->count());
        $this->assertEquals(2, $cart->items->first()->quantity); // Should remain 2, not add to 4
    }

    public function test_sync_cart_optimized_method(): void
    {
        // Test the optimized method
        $items = [
            ['product_id' => $this->product->id, 'quantity' => 3]
        ];
        
        $this->cartService->syncCartOptimized($this->user->id, $items);
        
        $cart = Cart::where('user_id', $this->user->id)->first();
        $this->assertEquals(1, $cart->items->count());
        $this->assertEquals(3, $cart->items->first()->quantity);
        
        // Add more quantity
        $this->cartService->syncCartOptimized($this->user->id, $items);
        
        $cart->refresh();
        $this->assertEquals(1, $cart->items->count());
        $this->assertEquals(6, $cart->items->first()->quantity);
    }

    public function test_sync_cart_with_multiple_products(): void
    {
        $product2 = Product::factory()->create();
        ProductPrice::factory()->create([
            'product_id' => $product2->id,
            'base_price' => 50.00,
        ]);
        
        $items = [
            ['product_id' => $this->product->id, 'quantity' => 2],
            ['product_id' => $product2->id, 'quantity' => 1],
        ];
        
        $this->cartService->syncCart($this->user->id, $items);
        
        $cart = Cart::where('user_id', $this->user->id)->first();
        $this->assertEquals(2, $cart->items->count());
        
        // Add more items (should replace quantities, not add)
        $items = [
            ['product_id' => $this->product->id, 'quantity' => 1],
            ['product_id' => $product2->id, 'quantity' => 2],
        ];
        
        $this->cartService->syncCart($this->user->id, $items);
        
        $cart->refresh();
        $this->assertEquals(2, $cart->items->count());
        
        $item1 = $cart->items->where('product_id', $this->product->id)->first();
        $item2 = $cart->items->where('product_id', $product2->id)->first();
        
        $this->assertEquals(1, $item1->quantity); // Should be 1, not 3
        $this->assertEquals(2, $item2->quantity); // Should be 2, not 3
    }

    public function test_sync_cart_handles_invalid_product(): void
    {
        $items = [
            ['product_id' => 99999, 'quantity' => 1], // Non-existent product
            ['product_id' => $this->product->id, 'quantity' => 2],
        ];
        
        // Should not throw exception, just skip invalid product
        $this->cartService->syncCart($this->user->id, $items);
        
        $cart = Cart::where('user_id', $this->user->id)->first();
        $this->assertEquals(1, $cart->items->count());
        $this->assertEquals($this->product->id, $cart->items->first()->product_id);
    }

    public function test_sync_cart_requires_user_id(): void
    {
        $items = [
            ['product_id' => $this->product->id, 'quantity' => 1]
        ];
        
        // Should throw exception when user_id is null or 0
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('User ID is required for cart syncing');
        
        $this->cartService->syncCart(0, $items);
    }
} 