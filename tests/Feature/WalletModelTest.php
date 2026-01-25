<?php

use App\Enums\WalletTransactionDirection;
use App\Enums\WalletTransactionType;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('wallets table has expected columns', function () {
    expect(Schema::hasColumns('wallets', [
        'id',
        'user_id',
        'balance',
        'currency',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
});

test('wallet belongs to user', function () {
    $user = User::factory()->create();
    $wallet = Wallet::create([
        'user_id' => $user->id,
        'balance' => 0,
        'currency' => 'USD',
    ]);

    expect($wallet->user)->not->toBeNull();
    expect($wallet->user->is($user))->toBeTrue();
});

test('wallet has many transactions', function () {
    $user = User::factory()->create();
    $wallet = Wallet::create([
        'user_id' => $user->id,
        'balance' => 0,
        'currency' => 'USD',
    ]);

    $transaction = WalletTransaction::create([
        'wallet_id' => $wallet->id,
        'type' => WalletTransactionType::Topup,
        'direction' => WalletTransactionDirection::Credit,
        'amount' => 10,
        'status' => WalletTransaction::STATUS_POSTED,
    ]);

    expect($wallet->transactions->pluck('id'))->toContain($transaction->id);
});
