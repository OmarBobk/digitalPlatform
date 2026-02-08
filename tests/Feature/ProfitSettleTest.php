<?php

use App\Enums\FulfillmentStatus;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\WalletTransactionDirection;
use App\Enums\WalletTransactionType;
use App\Enums\WalletType;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Package;
use App\Models\Product;
use App\Models\Settlement;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('profit settle dry-run reports eligible fulfillments', function () {
    $user = User::factory()->create();
    $wallet = Wallet::create([
        'user_id' => $user->id,
        'type' => WalletType::Customer,
        'balance' => 500,
        'currency' => 'USD',
    ]);

    $package = Package::factory()->create();
    $product = Product::factory()->create([
        'package_id' => $package->id,
        'entry_price' => 10,
    ]);

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

    $fulfillment = Fulfillment::create([
        'order_id' => $order->id,
        'order_item_id' => $item->id,
        'provider' => 'manual',
        'status' => FulfillmentStatus::Completed,
        'attempts' => 1,
        'completed_at' => now(),
    ]);

    $this->artisan('profit:settle', ['--dry-run' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('would settle 1 fulfillment');
});

test('profit settle credits platform wallet and creates settlement', function () {
    $user = User::factory()->create();
    $wallet = Wallet::create([
        'user_id' => $user->id,
        'type' => WalletType::Customer,
        'balance' => 500,
        'currency' => 'USD',
    ]);

    $package = Package::factory()->create();
    $product = Product::factory()->create([
        'package_id' => $package->id,
        'entry_price' => 10,
    ]);

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

    $fulfillment = Fulfillment::create([
        'order_id' => $order->id,
        'order_item_id' => $item->id,
        'provider' => 'manual',
        'status' => FulfillmentStatus::Completed,
        'attempts' => 1,
        'completed_at' => now(),
    ]);

    $platformWallet = Wallet::forPlatform();
    $balanceBefore = (float) $platformWallet->balance;

    $this->artisan('profit:settle')->assertSuccessful();

    $platformWallet->refresh();
    expect((float) $platformWallet->balance)->toBe($balanceBefore + 5.0);

    $settlement = Settlement::query()->latest()->first();
    expect($settlement)->not->toBeNull();
    expect((float) $settlement->total_amount)->toBe(5.0);
    expect($settlement->fulfillments)->toHaveCount(1);

    $tx = WalletTransaction::query()
        ->where('wallet_id', $platformWallet->id)
        ->where('type', WalletTransactionType::Settlement)
        ->where('reference_type', Settlement::class)
        ->first();
    expect($tx)->not->toBeNull();
    expect((float) $tx->amount)->toBe(5.0);
    expect($tx->direction)->toBe(WalletTransactionDirection::Credit);
});

test('profit settle excludes fulfillments with null entry_price', function () {
    $package = Package::factory()->create();
    $product = Product::factory()->create([
        'package_id' => $package->id,
        'entry_price' => null,
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
        'entry_price' => null,
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

    $this->artisan('profit:settle', ['--dry-run' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('No eligible fulfillments');
});

test('create order snapshots entry_price on order items', function () {
    $user = User::factory()->create();
    $package = Package::factory()->create();
    $product = Product::factory()->create([
        'package_id' => $package->id,
        'entry_price' => 25,
    ]);

    $order = app(\App\Actions\Orders\CreateOrderFromCartPayload::class)->handle($user, [
        ['product_id' => $product->id, 'quantity' => 1],
    ]);

    $item = $order->items->first();
    expect($item)->not->toBeNull();
    expect((float) $item->entry_price)->toBe(25.0);
});
