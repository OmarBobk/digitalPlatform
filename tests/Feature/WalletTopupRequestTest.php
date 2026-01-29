<?php

use App\Enums\TopupMethod;
use App\Enums\TopupRequestStatus;
use App\Models\TopupRequest;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('user can create a topup request from wallet page', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::frontend.wallet')
        ->set('topupAmount', '25')
        ->set('topupMethod', TopupMethod::ShamCash->value)
        ->call('submitTopup')
        ->assertSet('noticeMessage', __('messages.topup_request_created'));

    $topupRequest = TopupRequest::query()->first();

    expect($topupRequest)->not->toBeNull();
    expect($topupRequest->status)->toBe(TopupRequestStatus::Pending);

    $transaction = WalletTransaction::query()
        ->where('reference_type', TopupRequest::class)
        ->where('reference_id', $topupRequest->id)
        ->first();

    expect($transaction)->not->toBeNull();
    expect($transaction->status)->toBe(WalletTransaction::STATUS_PENDING);
});

test('user cannot create a second pending topup request', function () {
    $user = User::factory()->create();
    $wallet = \App\Models\Wallet::forUser($user);

    TopupRequest::create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'method' => TopupMethod::ShamCash,
        'amount' => 20,
        'currency' => $wallet->currency,
        'status' => TopupRequestStatus::Pending,
    ]);

    Livewire::actingAs($user)
        ->test('pages::frontend.wallet')
        ->set('topupAmount', '30')
        ->set('topupMethod', TopupMethod::ShamCash->value)
        ->call('submitTopup')
        ->assertSet('noticeMessage', __('messages.topup_request_pending'));

    expect(TopupRequest::query()->where('user_id', $user->id)->count())->toBe(1);
});
