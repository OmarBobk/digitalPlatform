<?php

use App\Actions\Orders\PayOrderWithWallet;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Package;
use App\Models\Product;
use App\Models\SystemEvent;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $role = Role::firstOrCreate(['name' => 'admin']);
    $role->syncPermissions([
        Permission::firstOrCreate(['name' => 'view_activities']),
    ]);
});

test('pay order with wallet records financial system event', function () {
    $user = User::factory()->create();
    $package = Package::factory()->create();
    $product = Product::factory()->create([
        'package_id' => $package->id,
        'entry_price' => 100,
    ]);
    $wallet = Wallet::create([
        'user_id' => $user->id,
        'balance' => 150,
        'currency' => 'USD',
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

    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'package_id' => $package->id,
        'name' => $product->name,
        'unit_price' => 100,
        'quantity' => 1,
        'line_total' => 100,
        'status' => OrderItemStatus::Pending,
    ]);

    $action = new PayOrderWithWallet;
    $action->handle($order, $wallet);

    $event = SystemEvent::query()
        ->where('event_type', 'wallet.purchase.debited')
        ->where('entity_type', Order::class)
        ->where('entity_id', $order->id)
        ->first();

    expect($event)->not->toBeNull();
    expect($event->is_financial)->toBeTrue();
    expect($event->meta)->toHaveKey('amount');
    expect($event->meta)->toHaveKey('wallet_id');
    expect($event->meta)->toHaveKey('transaction_id');
});

test('admin can view system events page', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('admin.system-events.index'))
        ->assertOk()
        ->assertSee(__('messages.system_events'));
});

test('non-admin cannot access system events page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.system-events.index'))
        ->assertNotFound();
});

test('system event is insert-only update and delete throw', function () {
    $event = SystemEvent::query()->create([
        'event_type' => 'test.insert_only',
        'entity_type' => User::class,
        'entity_id' => 1,
        'severity' => 'info',
        'is_financial' => false,
    ]);

    expect(fn () => $event->update(['event_type' => 'changed']))->toThrow(\BadMethodCallException::class, 'Updates are not allowed');
    expect(fn () => $event->delete())->toThrow(\BadMethodCallException::class, 'Deletes are not allowed');
});
