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

function makeOrderForUser(User $user, FulfillmentStatus $status): Order
{
    $package = Package::factory()->create();
    $product = Product::factory()->create([
        'package_id' => $package->id,
        'entry_price' => 40,
    ]);

    $order = Order::create([
        'user_id' => $user->id,
        'order_number' => Order::temporaryOrderNumber(),
        'currency' => 'USD',
        'subtotal' => 40,
        'fee' => 0,
        'total' => 40,
        'status' => OrderStatus::Paid,
    ]);

    $item = OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'package_id' => $package->id,
        'name' => $product->name,
        'unit_price' => 40,
        'quantity' => 1,
        'line_total' => 40,
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

test('user sees only their orders', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $order = makeOrderForUser($user, FulfillmentStatus::Queued);
    $otherOrder = makeOrderForUser($otherUser, FulfillmentStatus::Queued);

    $this->actingAs($user)
        ->get('/orders')
        ->assertOk()
        ->assertSee($order->order_number)
        ->assertDontSee($otherOrder->order_number)
        ->assertDontSee(__('messages.request_refund'));
});
