<?php

use App\Enums\WalletTransactionDirection;
use App\Enums\WalletTransactionType;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('wallet transactions table has expected columns', function () {
    expect(Schema::hasColumns('wallet_transactions', [
        'id',
        'wallet_id',
        'type',
        'direction',
        'amount',
        'status',
        'reference_type',
        'reference_id',
        'meta',
        'created_at',
    ]))->toBeTrue();
});

test('wallet transactions table does not include updated_at', function () {
    expect(Schema::hasColumn('wallet_transactions', 'updated_at'))->toBeFalse();
});

test('wallet transaction belongs to wallet', function () {
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
        'amount' => 25,
        'status' => WalletTransaction::STATUS_POSTED,
    ]);

    expect($transaction->wallet)->not->toBeNull();
    expect($transaction->wallet->is($wallet))->toBeTrue();
});

test('wallet transaction morphs to reference model', function () {
    $user = User::factory()->create();
    $wallet = Wallet::create([
        'user_id' => $user->id,
        'balance' => 0,
        'currency' => 'USD',
    ]);

    $referenceUser = User::factory()->create();
    $transaction = WalletTransaction::create([
        'wallet_id' => $wallet->id,
        'type' => WalletTransactionType::Adjustment,
        'direction' => WalletTransactionDirection::Credit,
        'amount' => 5,
        'status' => WalletTransaction::STATUS_POSTED,
        'reference_type' => User::class,
        'reference_id' => $referenceUser->id,
    ]);

    expect($transaction->reference)->not->toBeNull();
    expect($transaction->reference->is($referenceUser))->toBeTrue();
});
