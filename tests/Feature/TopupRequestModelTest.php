<?php

use App\Enums\TopupMethod;
use App\Enums\TopupRequestStatus;
use App\Enums\WalletTransactionDirection;
use App\Enums\WalletTransactionType;
use App\Models\TopupProof;
use App\Models\TopupRequest;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('topup requests table has expected columns', function () {
    expect(Schema::hasColumns('topup_requests', [
        'id',
        'user_id',
        'wallet_id',
        'method',
        'amount',
        'currency',
        'status',
        'note',
        'approved_by',
        'approved_at',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
});

test('topup request belongs to user and wallet', function () {
    $user = User::factory()->create();
    $wallet = Wallet::create([
        'user_id' => $user->id,
        'balance' => 0,
        'currency' => 'USD',
    ]);

    $request = TopupRequest::create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'method' => TopupMethod::ShamCash,
        'amount' => 25,
        'currency' => 'USD',
        'status' => TopupRequestStatus::Pending,
    ]);

    expect($request->user->is($user))->toBeTrue();
    expect($request->wallet->is($wallet))->toBeTrue();
});

test('topup request has many proofs', function () {
    $user = User::factory()->create();
    $wallet = Wallet::create([
        'user_id' => $user->id,
        'balance' => 0,
        'currency' => 'USD',
    ]);

    $request = TopupRequest::create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'method' => TopupMethod::EftTransfer,
        'amount' => 40,
        'currency' => 'USD',
        'status' => TopupRequestStatus::Pending,
    ]);

    $proof = TopupProof::create([
        'topup_request_id' => $request->id,
        'file_path' => 'topups/sample.pdf',
    ]);

    expect($request->proofs->pluck('id'))->toContain($proof->id);
});

test('creating a topup request creates a pending wallet transaction', function () {
    $user = User::factory()->create();
    $wallet = Wallet::create([
        'user_id' => $user->id,
        'balance' => 0,
        'currency' => 'USD',
    ]);

    $request = TopupRequest::create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'method' => TopupMethod::ShamCash,
        'amount' => 75,
        'currency' => 'USD',
        'status' => TopupRequestStatus::Pending,
        'note' => 'WhatsApp proof sent',
    ]);

    $transaction = WalletTransaction::query()
        ->where('reference_type', TopupRequest::class)
        ->where('reference_id', $request->id)
        ->first();

    expect($transaction)->not->toBeNull();
    expect($transaction->status)->toBe(WalletTransaction::STATUS_PENDING);
    expect($transaction->type)->toBe(WalletTransactionType::Topup);
    expect($transaction->direction)->toBe(WalletTransactionDirection::Credit);

    $wallet->refresh();
    expect((float) $wallet->balance)->toBe(0.0);
});
