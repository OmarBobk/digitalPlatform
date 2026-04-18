<?php

use App\Enums\FulfillmentStatus;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\ProductAmountMode;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Package;
use App\Models\PackageRequirement;
use App\Models\Product;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
});

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
        ->assertDontSee(__('messages.request_refund'))
        ->assertSee(__('messages.order_status_paid'), false)
        ->assertSee(__('messages.view_order'), false)
        ->assertSee(route('orders.show', $order->order_number), false);
});

test('orders list shows purchased amount for custom amount line items', function () {
    $user = User::factory()->create();
    $package = Package::factory()->create();
    $product = Product::factory()->create([
        'package_id' => $package->id,
        'entry_price' => 0.01,
        'amount_mode' => ProductAmountMode::Custom,
    ]);

    $order = Order::create([
        'user_id' => $user->id,
        'order_number' => Order::temporaryOrderNumber(),
        'currency' => 'USD',
        'subtotal' => 66.90,
        'fee' => 0,
        'total' => 66.90,
        'status' => OrderStatus::Paid,
    ]);

    $item = OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'package_id' => $package->id,
        'name' => $product->name,
        'unit_price' => 0.01,
        'quantity' => 1,
        'amount_mode' => ProductAmountMode::Custom,
        'requested_amount' => 6_690,
        'amount_unit_label' => 'Crystal',
        'line_total' => 66.90,
        'status' => OrderItemStatus::Pending,
    ]);

    Fulfillment::create([
        'order_id' => $order->id,
        'order_item_id' => $item->id,
        'provider' => 'manual',
        'status' => FulfillmentStatus::Queued,
        'attempts' => 0,
    ]);

    $this->actingAs($user)
        ->get('/orders')
        ->assertOk()
        ->assertSee(__('messages.order_item_purchased_amount'), false)
        ->assertSee(number_format(6_690), false);
});

test('orders list shows refund status badge when fulfillment failed', function () {
    $user = User::factory()->create();
    $order = makeOrderForUser($user, FulfillmentStatus::Failed);

    $this->actingAs($user)
        ->get('/orders')
        ->assertOk()
        ->assertSee(__('messages.refund'))
        ->assertSee('data-test="order-card-request-refund"', false);

    $fulfillment = $order->items()->first()->fulfillments()->first();
    $fulfillment->update([
        'meta' => [
            'refund' => [
                'status' => WalletTransaction::STATUS_PENDING,
            ],
        ],
    ]);

    $this->actingAs($user)
        ->get('/orders')
        ->assertOk()
        ->assertSee(__('messages.refund_requested'));

    $fulfillment->update([
        'meta' => [
            'refund' => [
                'status' => WalletTransaction::STATUS_POSTED,
            ],
        ],
    ]);

    $this->actingAs($user)
        ->get('/orders')
        ->assertOk()
        ->assertSee(__('messages.refunded'));
});

test('orders list refund button requests refund for failed fulfillment', function () {
    $user = User::factory()->create();
    Wallet::forUser($user);
    $order = makeOrderForUser($user, FulfillmentStatus::Failed);

    Livewire::actingAs($user)
        ->test('pages::frontend.orders')
        ->call('requestRefundForOrder', $order->id);

    $fulfillment = $order->items()->first()->fulfillments()->first();

    expect(data_get($fulfillment->fresh()->meta, 'refund.status'))->toBe(WalletTransaction::STATUS_PENDING);
});

test('orders list shows package requirement labels for requirement keys', function () {
    $user = User::factory()->create();
    $package = Package::factory()->create();
    PackageRequirement::factory()->create([
        'package_id' => $package->id,
        'key' => 'id',
        'label' => 'Player display name',
        'type' => 'string',
        'is_required' => true,
        'order' => 1,
    ]);
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
        'status' => OrderItemStatus::Pending,
        'requirements_payload' => ['id' => 'abc-123'],
    ]);

    Fulfillment::create([
        'order_id' => $order->id,
        'order_item_id' => $item->id,
        'provider' => 'manual',
        'status' => FulfillmentStatus::Queued,
        'attempts' => 0,
    ]);

    $this->actingAs($user)
        ->get('/orders')
        ->assertOk()
        ->assertSee('Player display name:', false)
        ->assertSee('abc-123', false);
});

test('order details shows package requirement labels for requirement keys', function () {
    $user = User::factory()->create();
    $package = Package::factory()->create();
    PackageRequirement::factory()->create([
        'package_id' => $package->id,
        'key' => 'id',
        'label' => 'Account UID',
        'type' => 'string',
        'is_required' => true,
        'order' => 1,
    ]);
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
        'status' => OrderItemStatus::Pending,
        'requirements_payload' => ['id' => 'uid-999'],
    ]);

    Fulfillment::create([
        'order_id' => $order->id,
        'order_item_id' => $item->id,
        'provider' => 'manual',
        'status' => FulfillmentStatus::Queued,
        'attempts' => 0,
    ]);

    $this->actingAs($user)
        ->get(route('orders.show', $order->order_number))
        ->assertOk()
        ->assertSee('Account UID', false)
        ->assertSee('uid-999', false);
});
