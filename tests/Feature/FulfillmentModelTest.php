<?php

use App\Enums\FulfillmentLogLevel;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Models\Fulfillment;
use App\Models\FulfillmentLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Package;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('fulfillments table has expected columns', function () {
    expect(Schema::hasColumns('fulfillments', [
        'id',
        'order_id',
        'order_item_id',
        'provider',
        'status',
        'attempts',
        'last_error',
        'processed_at',
        'completed_at',
        'meta',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
});

test('fulfillment logs table has expected columns', function () {
    expect(Schema::hasColumns('fulfillment_logs', [
        'id',
        'fulfillment_id',
        'level',
        'message',
        'context',
        'created_at',
    ]))->toBeTrue();
});

test('fulfillment belongs to order and order item and has logs', function () {
    $user = User::factory()->create();
    $package = Package::factory()->create();
    $product = Product::factory()->create(['package_id' => $package->id]);

    $order = Order::create([
        'user_id' => $user->id,
        'order_number' => Order::temporaryOrderNumber(),
        'currency' => 'USD',
        'subtotal' => 10,
        'fee' => 0,
        'total' => 10,
        'status' => OrderStatus::Paid,
    ]);

    $item = OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'package_id' => $package->id,
        'name' => $product->name,
        'unit_price' => 10,
        'quantity' => 1,
        'line_total' => 10,
        'status' => OrderItemStatus::Pending,
    ]);

    $fulfillment = Fulfillment::create([
        'order_id' => $order->id,
        'order_item_id' => $item->id,
        'provider' => 'manual',
        'status' => FulfillmentStatus::Queued,
        'attempts' => 0,
    ]);

    $log = FulfillmentLog::create([
        'fulfillment_id' => $fulfillment->id,
        'level' => FulfillmentLogLevel::Info,
        'message' => 'Fulfillment queued',
    ]);

    expect($fulfillment->order->is($order))->toBeTrue();
    expect($fulfillment->orderItem->is($item))->toBeTrue();
    expect($fulfillment->logs->pluck('id'))->toContain($log->id);
});
