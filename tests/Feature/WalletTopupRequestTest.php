<?php

use App\Actions\Topups\CreateTopupRequestAction;
use App\Enums\TopupMethod;
use App\Enums\TopupRequestStatus;
use App\Events\TopupRequestsChanged;
use App\Models\TopupProof;
use App\Models\TopupRequest;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
});

test('user can create a topup request with proof from wallet page', function () {
    Storage::fake('local');
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::frontend.wallet')
        ->set('topupAmount', '25')
        ->set('topupMethod', TopupMethod::ShamCash->value)
        ->set('proofFile', UploadedFile::fake()->image('proof.jpg'))
        ->call('submitTopup')
        ->assertSet('noticeMessage', __('messages.topup_request_created'));

    $topupRequest = TopupRequest::query()->first();

    expect($topupRequest)->not->toBeNull();
    expect($topupRequest->status)->toBe(TopupRequestStatus::Pending);

    $proof = TopupProof::query()->where('topup_request_id', $topupRequest->id)->first();
    expect($proof)->not->toBeNull();
    expect(Storage::disk('local')->exists($proof->file_path))->toBeTrue();

    $transaction = WalletTransaction::query()
        ->where('reference_type', TopupRequest::class)
        ->where('reference_id', $topupRequest->id)
        ->first();

    expect($transaction)->not->toBeNull();
    expect($transaction->status)->toBe(WalletTransaction::STATUS_PENDING);
});

test('creating a topup request broadcasts change event', function () {
    Storage::fake('local');
    Event::fake([TopupRequestsChanged::class]);

    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::frontend.wallet')
        ->set('topupAmount', '40')
        ->set('topupMethod', TopupMethod::ShamCash->value)
        ->set('proofFile', UploadedFile::fake()->image('proof.jpg'))
        ->call('submitTopup');

    $request = TopupRequest::query()->first();

    expect($request)->not->toBeNull();

    Event::assertDispatched(TopupRequestsChanged::class, function (TopupRequestsChanged $event) use ($request): bool {
        return $event->reason === 'created'
            && $event->topupRequestId === $request->id;
    });
});

test('user cannot create a second pending topup request', function () {
    $user = User::factory()->create();
    $wallet = \App\Models\Wallet::forUser($user);

    app(CreateTopupRequestAction::class)->handle([
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
        ->set('proofFile', UploadedFile::fake()->image('proof.jpg'))
        ->call('submitTopup')
        ->assertSet('noticeMessage', __('messages.topup_request_pending'));

    expect(TopupRequest::query()->where('user_id', $user->id)->count())->toBe(1);
});

test('submit topup without proof fails validation', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::frontend.wallet')
        ->set('topupAmount', '25')
        ->set('topupMethod', TopupMethod::ShamCash->value)
        ->call('submitTopup')
        ->assertHasErrors('proofFile');

    expect(TopupRequest::query()->count())->toBe(0);
});

test('submit topup with invalid file type fails validation', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::frontend.wallet')
        ->set('topupAmount', '25')
        ->set('topupMethod', TopupMethod::ShamCash->value)
        ->set('proofFile', UploadedFile::fake()->create('proof.txt', 100, 'text/plain'))
        ->call('submitTopup')
        ->assertHasErrors('proofFile');

    expect(TopupRequest::query()->count())->toBe(0);
});

test('submit topup with file exceeding max size fails validation', function () {
    $user = User::factory()->create();

    $oversizedFile = UploadedFile::fake()->create('proof.pdf', 6 * 1024 * 1024, 'application/pdf');

    Livewire::actingAs($user)
        ->test('pages::frontend.wallet')
        ->set('topupAmount', '25')
        ->set('topupMethod', TopupMethod::ShamCash->value)
        ->set('proofFile', $oversizedFile)
        ->call('submitTopup')
        ->assertHasErrors('proofFile');

    expect(TopupRequest::query()->count())->toBe(0);
});

test('valid proof creates request and single proof record', function () {
    Storage::fake('local');
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::frontend.wallet')
        ->set('topupAmount', '10')
        ->set('topupMethod', TopupMethod::EftTransfer->value)
        ->set('proofFile', UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf'))
        ->call('submitTopup')
        ->assertSet('noticeMessage', __('messages.topup_request_created'));

    $topupRequest = TopupRequest::query()->first();
    expect($topupRequest)->not->toBeNull();
    expect($topupRequest->proofs()->count())->toBe(1);
});
