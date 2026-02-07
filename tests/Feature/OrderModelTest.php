<?php

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Package;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('orders table has expected columns', function () {
    expect(Schema::hasColumns('orders', [
        'id',
        'user_id',
        'order_number',
        'currency',
        'subtotal',
        'fee',
        'total',
        'status',
        'paid_at',
        'meta',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
});

test('order items table has expected columns', function () {
    expect(Schema::hasColumns('order_items', [
        'id',
        'order_id',
        'product_id',
        'package_id',
        'name',
        'unit_price',
        'quantity',
        'line_total',
        'requirements_payload',
        'status',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
});

test('order belongs to user and has items', function () {
    $user = User::factory()->create();
    $package = Package::factory()->create();
    $product = Product::factory()->create([
        'package_id' => $package->id,
        'entry_price' => 50,
    ]);

    $order = Order::create([
        'user_id' => $user->id,
        'order_number' => Order::temporaryOrderNumber(),
        'currency' => 'USD',
        'subtotal' => 100,
        'fee' => 0,
        'total' => 100,
        'status' => OrderStatus::PendingPayment,
    ]);

    $orderItem = OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'package_id' => $package->id,
        'name' => $product->name,
        'unit_price' => 50,
        'quantity' => 2,
        'line_total' => 100,
        'status' => OrderItemStatus::Pending,
    ]);

    expect($order->user->is($user))->toBeTrue();
    expect($order->items->pluck('id'))->toContain($orderItem->id);
});

test('order item belongs to order', function () {
    $user = User::factory()->create();
    $package = Package::factory()->create();
    $product = Product::factory()->create([
        'package_id' => $package->id,
        'entry_price' => 30,
    ]);

    $order = Order::create([
        'user_id' => $user->id,
        'order_number' => Order::temporaryOrderNumber(),
        'currency' => 'USD',
        'subtotal' => 30,
        'fee' => 0,
        'total' => 30,
        'status' => OrderStatus::PendingPayment,
    ]);

    $orderItem = OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'package_id' => $package->id,
        'name' => $product->name,
        'unit_price' => 30,
        'quantity' => 1,
        'line_total' => 30,
        'status' => OrderItemStatus::Pending,
    ]);

    expect($orderItem->order->is($order))->toBeTrue();
});
