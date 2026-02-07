<?php

use App\Enums\FulfillmentStatus;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Package;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{order: Order, item: OrderItem, fulfillment: Fulfillment}
 */
function makeCompletedOrder(User $user, array $payload): array
{
    $package = Package::factory()->create();
    $product = Product::factory()->create([
        'package_id' => $package->id,
        'entry_price' => 25,
    ]);

    $order = Order::create([
        'user_id' => $user->id,
        'order_number' => Order::temporaryOrderNumber(),
        'currency' => 'USD',
        'subtotal' => 25,
        'fee' => 0,
        'total' => 25,
        'status' => OrderStatus::Paid,
    ]);

    $item = OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'package_id' => $package->id,
        'name' => $product->name,
        'unit_price' => 25,
        'quantity' => 1,
        'line_total' => 25,
        'requirements_payload' => ['player_id' => '12345'],
        'status' => OrderItemStatus::Fulfilled,
    ]);

    $fulfillment = Fulfillment::create([
        'order_id' => $order->id,
        'order_item_id' => $item->id,
        'provider' => 'manual',
        'status' => FulfillmentStatus::Completed,
        'attempts' => 1,
        'meta' => ['delivered_payload' => $payload],
    ]);

    return [
        'order' => $order,
        'item' => $item,
        'fulfillment' => $fulfillment,
    ];
}

function makeOrderWithItem(User $user, FulfillmentStatus $status): Order
{
    $package = Package::factory()->create();
    $product = Product::factory()->create([
        'package_id' => $package->id,
        'entry_price' => 10,
    ]);

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
        'status' => $status === FulfillmentStatus::Failed ? OrderItemStatus::Failed : OrderItemStatus::Pending,
    ]);

    Fulfillment::create([
        'order_id' => $order->id,
        'order_item_id' => $item->id,
        'provider' => 'manual',
        'status' => $status,
        'attempts' => 0,
    ]);

    return $order;
}

test('order details page renders for owner', function () {
    $user = User::factory()->create();
    $order = Order::create([
        'user_id' => $user->id,
        'order_number' => Order::temporaryOrderNumber(),
        'currency' => 'USD',
        'subtotal' => 10,
        'fee' => 0,
        'total' => 10,
        'status' => OrderStatus::Paid,
    ]);

    $this->actingAs($user)
        ->get(route('orders.show', $order->order_number))
        ->assertOk()
        ->assertSee($order->order_number)
        ->assertSee('data-test="back-button"', false);
});

test('order details page is forbidden for other users', function () {
    $owner = User::factory()->create();
    $payload = makeCompletedOrder($owner, ['code' => 'ABC-12345']);

    $otherUser = User::factory()->create();

    $this->actingAs($otherUser)
        ->get(route('orders.show', $payload['order']->order_number))
        ->assertForbidden();
});

test('delivered payload renders masked by default', function () {
    $user = User::factory()->create();
    $payload = [
        'code' => 'ABC-12345',
        'pin' => '9876',
        'server' => 'EU',
    ];
    $orderPayload = makeCompletedOrder($user, $payload);

    $maskedCode = '\\\\u2022\\\\u2022\\\\u2022\\\\u2022\\\\u2022';
    $maskedPin = '\\\\u2022\\\\u2022\\\\u2022\\\\u2022';

    $this->actingAs($user)
        ->get(route('orders.show', $orderPayload['order']->order_number))
        ->assertOk()
        ->assertSee($maskedCode, false)
        ->assertSee($maskedPin, false)
        ->assertDontSee($payload['code'], false)
        ->assertDontSee($payload['pin'], false)
        ->assertSee($payload['server']);
});

test('order details page shows refund actions only for failed items', function () {
    $user = User::factory()->create();
    $order = makeOrderWithItem($user, FulfillmentStatus::Queued);

    $this->actingAs($user)
        ->get(route('orders.show', $order->order_number))
        ->assertOk()
        ->assertDontSee(__('messages.request_refund'))
        ->assertDontSee(__('messages.retry_fulfillment'))
        ->assertDontSee('requestRefund')
        ->assertDontSee('retryFulfillment');

    $failedOrder = makeOrderWithItem($user, FulfillmentStatus::Failed);

    $this->actingAs($user)
        ->get(route('orders.show', $failedOrder->order_number))
        ->assertOk()
        ->assertSee(__('messages.request_refund'))
        ->assertSee(__('messages.retry'));
});
