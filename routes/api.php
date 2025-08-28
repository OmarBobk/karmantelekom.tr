<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Events\ShopCreated;
use App\Events\ShopAssigned;
use App\Events\OrderCreated;
use App\Events\OrderUpdated;
use App\Models\Shop;
use App\Models\Order;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Demo routes for broadcasting testing
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/demo/shop-created', function () {
        $shop = Shop::factory()->create([
            'name' => 'Demo Shop - ' . now()->format('H:i:s'),
            'phone' => '+90 555 123 4567',
            'address' => 'Demo Address, Istanbul',
        ]);

        event(new ShopCreated($shop, auth()->id()));

        return response()->json([
            'success' => true,
            'message' => 'Shop created event dispatched',
            'shop' => $shop
        ]);
    });

    Route::post('/demo/shop-assigned', function () {
        $shop = Shop::first() ?? Shop::factory()->create();
        $salesperson = User::first() ?? User::factory()->create();

        event(new ShopAssigned($shop, $salesperson, auth()->user()));

        return response()->json([
            'success' => true,
            'message' => 'Shop assigned event dispatched',
            'shop' => $shop,
            'salesperson' => $salesperson
        ]);
    });

    Route::post('/demo/order-created', function () {
        $order = Order::factory()->create([
            'total_price' => rand(100, 1000),
            'notes' => 'Demo order created at ' . now()->format('H:i:s'),
        ]);

        event(new OrderCreated($order, auth()->id()));

        return response()->json([
            'success' => true,
            'message' => 'Order created event dispatched',
            'order' => $order
        ]);
    });

    Route::post('/demo/order-updated', function () {
        $order = Order::first() ?? Order::factory()->create();
        
        $originalData = [
            'status' => $order->status,
            'total_price' => $order->total_price,
            'shop_id' => $order->shop_id,
            'notes' => $order->notes,
        ];

        $order->update([
            'total_price' => $order->total_price + rand(10, 100),
            'notes' => 'Updated at ' . now()->format('H:i:s'),
        ]);

        event(new OrderUpdated($order, $originalData, auth()->id()));

        return response()->json([
            'success' => true,
            'message' => 'Order updated event dispatched',
            'order' => $order
        ]);
    });
});
