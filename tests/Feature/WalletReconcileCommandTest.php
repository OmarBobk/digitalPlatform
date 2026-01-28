<?php

use App\Enums\WalletTransactionDirection;
use App\Enums\WalletTransactionType;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

test('wallet reconcile reports drift in dry run', function () {
    $user = User::factory()->create();
    $wallet = Wallet::forUser($user);
    $wallet->update(['balance' => 5]);

    WalletTransaction::create([
        'wallet_id' => $wallet->id,
        'type' => WalletTransactionType::Topup,
        'direction' => WalletTransactionDirection::Credit,
        'amount' => 20,
        'status' => WalletTransaction::STATUS_POSTED,
    ]);

    WalletTransaction::create([
        'wallet_id' => $wallet->id,
        'type' => WalletTransactionType::Purchase,
        'direction' => WalletTransactionDirection::Debit,
        'amount' => 3,
        'status' => WalletTransaction::STATUS_POSTED,
    ]);

    Artisan::call('wallet:reconcile', [
        '--user' => $user->id,
        '--dry-run' => true,
    ]);

    expect(Artisan::output())->toContain(sprintf('Wallet %d (user %d):', $wallet->id, $user->id));
    $wallet->refresh();
    expect((float) $wallet->balance)->toBe(5.0);
});

test('wallet reconcile fixes drift when not dry run', function () {
    $user = User::factory()->create();
    $wallet = Wallet::forUser($user);
    $wallet->update(['balance' => 0]);

    WalletTransaction::create([
        'wallet_id' => $wallet->id,
        'type' => WalletTransactionType::Topup,
        'direction' => WalletTransactionDirection::Credit,
        'amount' => 12,
        'status' => WalletTransaction::STATUS_POSTED,
    ]);

    Artisan::call('wallet:reconcile', [
        '--user' => $user->id,
    ]);

    $wallet->refresh();
    expect((float) $wallet->balance)->toBe(12.0);
    expect(Artisan::output())->toContain('Updated 1 wallet');
});
