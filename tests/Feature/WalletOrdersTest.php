<?php

use App\Enums\OrderStatus;
use App\Enums\WalletTransactionDirection;
use App\Enums\WalletTransactionType;
use App\Models\Order;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('wallet shows recent orders with link', function () {
    $user = User::factory()->create();

    $order = Order::create([
        'user_id' => $user->id,
        'order_number' => Order::temporaryOrderNumber(),
        'currency' => 'USD',
        'subtotal' => 50,
        'fee' => 0,
        'total' => 50,
        'status' => OrderStatus::Paid,
    ]);

    $wallet = Wallet::forUser($user);

    WalletTransaction::create([
        'wallet_id' => $wallet->id,
        'type' => WalletTransactionType::Purchase,
        'direction' => WalletTransactionDirection::Debit,
        'amount' => 50,
        'status' => WalletTransaction::STATUS_POSTED,
        'reference_type' => Order::class,
        'reference_id' => $order->id,
        'meta' => [
            'order_number' => $order->order_number,
        ],
    ]);

    $this->actingAs($user)
        ->get('/wallet')
        ->assertOk()
        ->assertSee('data-test="back-button"', false)
        ->assertSee($order->order_number)
        ->assertSee(__('messages.order_number').': '.$order->order_number)
        ->assertSee(route('orders.show', $order->order_number));
});
