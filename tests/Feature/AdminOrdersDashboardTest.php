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
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function makeAdminOrder(User $user): Order
{
    $package = Package::factory()->create();
    $product = Product::factory()->create([
        'package_id' => $package->id,
        'retail_price' => 50,
    ]);

    $order = Order::create([
        'user_id' => $user->id,
        'order_number' => Order::temporaryOrderNumber(),
        'currency' => 'USD',
        'subtotal' => 50,
        'fee' => 0,
        'total' => 50,
        'status' => OrderStatus::Paid,
    ]);

    $item = OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'package_id' => $package->id,
        'name' => $product->name,
        'unit_price' => 50,
        'quantity' => 1,
        'line_total' => 50,
        'status' => OrderItemStatus::Pending,
    ]);

    Fulfillment::create([
        'order_id' => $order->id,
        'order_item_id' => $item->id,
        'provider' => 'manual',
        'status' => FulfillmentStatus::Queued,
        'attempts' => 0,
    ]);

    return $order;
}

test('admin can view orders index and detail', function () {
    $role = Role::firstOrCreate(['name' => 'admin']);
    $admin = User::factory()->create();
    $admin->assignRole($role);

    $user = User::factory()->create();
    $order = makeAdminOrder($user);

    $this->actingAs($admin)
        ->get('/admin/orders')
        ->assertOk()
        ->assertSee($order->order_number);

    $this->actingAs($admin)
        ->get(route('admin.orders.show', $order))
        ->assertOk()
        ->assertSee($order->order_number);
});

test('admin order detail does not render refund action', function () {
    $role = Role::firstOrCreate(['name' => 'admin']);
    $admin = User::factory()->create();
    $admin->assignRole($role);

    $user = User::factory()->create();
    $order = makeAdminOrder($user);

    $this->actingAs($admin)
        ->get(route('admin.orders.show', $order))
        ->assertOk()
        ->assertDontSee(__('messages.refund_order'))
        ->assertDontSee('refundOrder');
});

test('non-admin cannot access admin orders pages', function () {
    $user = User::factory()->create();
    $order = makeAdminOrder($user);

    $this->actingAs($user)
        ->get('/admin/orders')
        ->assertRedirect('/404');

    $this->actingAs($user)
        ->get(route('admin.orders.show', $order))
        ->assertRedirect('/404');
});
