<?php

use App\Models\SystemEvent;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\OperationalIntelligenceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('wallet velocity detection triggers when 3 posted transactions within 60 seconds', function () {
    Carbon::setTestNow(Carbon::parse('2025-01-15 12:00:00'));

    $user = User::factory()->create();
    $wallet = Wallet::create([
        'user_id' => $user->id,
        'type' => 'customer',
        'balance' => 0,
        'currency' => 'USD',
    ]);

    WalletTransaction::query()->insert([
        [
            'wallet_id' => $wallet->id,
            'type' => 'purchase',
            'direction' => 'debit',
            'amount' => 10,
            'status' => WalletTransaction::STATUS_POSTED,
            'reference_type' => null,
            'reference_id' => null,
            'created_at' => Carbon::now()->subSeconds(30),
        ],
        [
            'wallet_id' => $wallet->id,
            'type' => 'purchase',
            'direction' => 'debit',
            'amount' => 20,
            'status' => WalletTransaction::STATUS_POSTED,
            'reference_type' => null,
            'reference_id' => null,
            'created_at' => Carbon::now()->subSeconds(10),
        ],
        [
            'wallet_id' => $wallet->id,
            'type' => 'purchase',
            'direction' => 'debit',
            'amount' => 30,
            'status' => WalletTransaction::STATUS_POSTED,
            'reference_type' => null,
            'reference_id' => null,
            'created_at' => Carbon::now(),
        ],
    ]);

    $thirdTx = WalletTransaction::query()
        ->where('wallet_id', $wallet->id)
        ->orderByDesc('id')
        ->first();

    app(OperationalIntelligenceService::class)->detectWalletVelocity($thirdTx);

    $event = SystemEvent::query()
        ->where('event_type', 'wallet.anomaly.velocity_detected')
        ->where('entity_type', Wallet::class)
        ->where('entity_id', $wallet->id)
        ->first();

    expect($event)->not->toBeNull();
    expect($event->is_financial)->toBeFalse();
    expect($event->severity->value)->toBe('warning');
    expect($event->meta)->toHaveKey('count');
    expect((int) $event->meta['count'])->toBe(3);

    Carbon::setTestNow();
});

test('duplicate velocity detection in same window does not create multiple events', function () {
    Carbon::setTestNow(Carbon::parse('2025-01-15 12:00:00'));

    $user = User::factory()->create();
    $wallet = Wallet::create([
        'user_id' => $user->id,
        'type' => 'customer',
        'balance' => 0,
        'currency' => 'USD',
    ]);

    WalletTransaction::query()->insert([
        [
            'wallet_id' => $wallet->id,
            'type' => 'purchase',
            'direction' => 'debit',
            'amount' => 10,
            'status' => WalletTransaction::STATUS_POSTED,
            'reference_type' => null,
            'reference_id' => null,
            'created_at' => Carbon::now(),
        ],
        [
            'wallet_id' => $wallet->id,
            'type' => 'purchase',
            'direction' => 'debit',
            'amount' => 20,
            'status' => WalletTransaction::STATUS_POSTED,
            'reference_type' => null,
            'reference_id' => null,
            'created_at' => Carbon::now(),
        ],
        [
            'wallet_id' => $wallet->id,
            'type' => 'purchase',
            'direction' => 'debit',
            'amount' => 30,
            'status' => WalletTransaction::STATUS_POSTED,
            'reference_type' => null,
            'reference_id' => null,
            'created_at' => Carbon::now(),
        ],
    ]);

    $txs = WalletTransaction::query()->where('wallet_id', $wallet->id)->orderBy('id')->get();
    $service = app(OperationalIntelligenceService::class);

    $service->detectWalletVelocity($txs[0]);
    $service->detectWalletVelocity($txs[1]);
    $service->detectWalletVelocity($txs[2]);

    $count = SystemEvent::query()
        ->where('event_type', 'wallet.anomaly.velocity_detected')
        ->where('entity_type', Wallet::class)
        ->where('entity_id', $wallet->id)
        ->count();

    expect($count)->toBe(1);

    Carbon::setTestNow();
});
