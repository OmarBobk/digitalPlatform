<?php

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\WalletTransactionDirection;
use App\Enums\WalletTransactionType;
use App\Enums\WalletType;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Package;
use App\Models\Product;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('checkout creates paid order and fulfillments from cart payload', function () {
    $user = User::factory()->create();
    $wallet = Wallet::create([
        'user_id' => $user->id,
        'type' => WalletType::Customer,
        'balance' => 300,
        'currency' => 'USD',
    ]);

    $package = Package::factory()->create();
    $product = Product::factory()->create([
        'package_id' => $package->id,
        'name' => 'Google Play 100$',
        'entry_price' => 120,
    ]);

    $payload = [
        [
            'id' => $product->id,
            'quantity' => 2,
            'price' => 1,
            'name' => 'Tampered',
        ],
    ];

    $component = Livewire::actingAs($user)
        ->test('pages::frontend.cart')
        ->call('checkout', $payload);

    $order = Order::query()->first();

    expect($order)->not->toBeNull();
    $component->assertSet('lastOrderNumber', $order?->order_number);
    expect($order->status)->toBe(OrderStatus::Paid);
    expect((float) $order->subtotal)->toBe(240.0);
    expect((float) $order->total)->toBe(240.0);

    $orderItem = OrderItem::query()->first();
    expect($orderItem)->not->toBeNull();
    expect($orderItem?->name)->toBe('Google Play 100$');

    $transaction = WalletTransaction::query()
        ->where('reference_type', Order::class)
        ->where('reference_id', $order->id)
        ->first();

    expect($transaction)->not->toBeNull();
    expect($transaction?->type)->toBe(WalletTransactionType::Purchase);
    expect($transaction?->direction)->toBe(WalletTransactionDirection::Debit);
    expect($transaction?->status)->toBe(WalletTransaction::STATUS_POSTED);

    $wallet->refresh();
    expect((float) $wallet->balance)->toBe(60.0);

    $fulfillments = Fulfillment::query()
        ->where('order_item_id', $orderItem->id)
        ->get();

    expect($fulfillments)->toHaveCount(2);
    expect($fulfillments->pluck('status')->unique()->all())->toBe([FulfillmentStatus::Queued]);
});

test('checkout fails when wallet balance is insufficient', function () {
    $user = User::factory()->create();

    $package = Package::factory()->create();
    $product = Product::factory()->create([
        'package_id' => $package->id,
        'entry_price' => 50,
    ]);

    $payload = [
        [
            'id' => $product->id,
            'quantity' => 1,
        ],
    ];

    Livewire::actingAs($user)
        ->test('pages::frontend.cart')
        ->call('checkout', $payload)
        ->assertSet('checkoutError', 'Insufficient wallet balance.');

    expect(Wallet::where('type', WalletType::Customer)->count())->toBe(1);
    expect(Order::count())->toBe(0);
    expect(WalletTransaction::count())->toBe(0);
});
