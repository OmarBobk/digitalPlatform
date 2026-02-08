<?php

use App\Enums\FulfillmentStatus;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Package;
use App\Models\Product;
use App\Models\Settlement;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('user without backend access cannot access settlements page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('settlements'))
        ->assertNotFound();
});

test('user with manage_settlements can view settlements page', function () {
    $permission = Permission::firstOrCreate(['name' => 'manage_settlements']);
    $role = Role::firstOrCreate(['name' => 'admin']);
    $role->givePermissionTo($permission);

    $admin = User::factory()->create();
    $admin->assignRole($role);

    $this->actingAs($admin)
        ->get(route('settlements'))
        ->assertOk()
        ->assertSee(__('messages.settlements'))
        ->assertSee(__('messages.settle_now'))
        ->assertSee(__('messages.platform_wallet_balance'));
});

test('settlements page shows platform wallet balance and settlements list', function () {
    $permission = Permission::firstOrCreate(['name' => 'manage_settlements']);
    $role = Role::firstOrCreate(['name' => 'admin']);
    $role->givePermissionTo($permission);

    $admin = User::factory()->create();
    $admin->assignRole($role);

    $platformWallet = Wallet::forPlatform();

    Livewire::actingAs($admin)
        ->test('pages::backend.settlements.index')
        ->assertSee((string) $platformWallet->balance)
        ->assertSee($platformWallet->currency);
});

test('settle now button runs settlement and credits platform wallet', function () {
    $permission = Permission::firstOrCreate(['name' => 'manage_settlements']);
    $role = Role::firstOrCreate(['name' => 'admin']);
    $role->givePermissionTo($permission);

    $admin = User::factory()->create();
    $admin->assignRole($role);

    $package = Package::factory()->create();
    $product = Product::factory()->create([
        'package_id' => $package->id,
        'entry_price' => 10,
    ]);

    $user = User::factory()->create();
    $order = Order::create([
        'user_id' => $user->id,
        'order_number' => Order::temporaryOrderNumber(),
        'currency' => 'USD',
        'subtotal' => 15,
        'fee' => 0,
        'total' => 15,
        'status' => OrderStatus::Paid,
    ]);

    $item = OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'package_id' => $package->id,
        'name' => $product->name,
        'unit_price' => 15,
        'entry_price' => 10,
        'quantity' => 1,
        'line_total' => 15,
        'status' => OrderItemStatus::Pending,
    ]);

    Fulfillment::create([
        'order_id' => $order->id,
        'order_item_id' => $item->id,
        'provider' => 'manual',
        'status' => FulfillmentStatus::Completed,
        'attempts' => 1,
        'completed_at' => now(),
    ]);

    $platformWallet = Wallet::forPlatform();
    $balanceBefore = (float) $platformWallet->balance;

    Livewire::actingAs($admin)
        ->test('pages::backend.settlements.index')
        ->call('runSettlement')
        ->assertSet('noticeVariant', 'success');

    $platformWallet->refresh();
    expect((float) $platformWallet->balance)->toBe($balanceBefore + 5.0);
    expect(Settlement::query()->count())->toBe(1);
});

test('settlement detail modal shows fulfillment breakdown', function () {
    $permission = Permission::firstOrCreate(['name' => 'manage_settlements']);
    $role = Role::firstOrCreate(['name' => 'admin']);
    $role->givePermissionTo($permission);

    $admin = User::factory()->create();
    $admin->assignRole($role);

    $package = Package::factory()->create();
    $product = Product::factory()->create([
        'package_id' => $package->id,
        'entry_price' => 10,
    ]);

    $user = User::factory()->create();
    $order = Order::create([
        'user_id' => $user->id,
        'order_number' => 'ORD-2026-000001',
        'currency' => 'USD',
        'subtotal' => 15,
        'fee' => 0,
        'total' => 15,
        'status' => OrderStatus::Paid,
    ]);

    $item = OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'package_id' => $package->id,
        'name' => 'Test Product',
        'unit_price' => 15,
        'entry_price' => 10,
        'quantity' => 1,
        'line_total' => 15,
        'status' => OrderItemStatus::Pending,
    ]);

    $fulfillment = Fulfillment::create([
        'order_id' => $order->id,
        'order_item_id' => $item->id,
        'provider' => 'manual',
        'status' => FulfillmentStatus::Completed,
        'attempts' => 1,
        'completed_at' => now(),
    ]);

    $this->artisan('profit:settle');

    $settlement = Settlement::query()->first();

    Livewire::actingAs($admin)
        ->test('pages::backend.settlements.index')
        ->call('openDetailModal', $settlement->id)
        ->assertSet('showDetailModal', true)
        ->assertSet('selectedSettlementId', $settlement->id)
        ->assertSee('ORD-2026-000001')
        ->assertSee('Test Product')
        ->assertSee('15.00')
        ->assertSee('10.00')
        ->assertSee('5.00');
});
