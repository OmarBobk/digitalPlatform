<?php

use App\Enums\TopupMethod;
use App\Enums\TopupRequestStatus;
use App\Models\TopupProof;
use App\Models\TopupRequest;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('topup proofs table has expected columns', function () {
    expect(Schema::hasColumns('topup_proofs', [
        'id',
        'topup_request_id',
        'file_path',
        'file_original_name',
        'mime_type',
        'size_bytes',
        'created_at',
    ]))->toBeTrue();
});

test('topup proof belongs to topup request', function () {
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
        'amount' => 30,
        'currency' => 'USD',
        'status' => TopupRequestStatus::Pending,
    ]);

    $proof = TopupProof::create([
        'topup_request_id' => $request->id,
        'file_path' => 'topups/proof.png',
    ]);

    expect($proof->topupRequest->is($request))->toBeTrue();
});
