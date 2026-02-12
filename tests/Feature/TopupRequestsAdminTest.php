<?php

use App\Actions\Topups\CreateTopupRequestAction;
use App\Enums\TopupMethod;
use App\Enums\TopupRequestStatus;
use App\Models\TopupRequest;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('admin can approve topup and credit wallet', function () {
    $permission = Permission::firstOrCreate(['name' => 'manage_topups']);
    $role = Role::firstOrCreate(['name' => 'admin']);
    $role->givePermissionTo($permission);

    $admin = User::factory()->create();
    $admin->assignRole($role);

    $customer = User::factory()->create();
    $wallet = Wallet::forUser($customer);

    $topupRequest = app(CreateTopupRequestAction::class)->handle([
        'user_id' => $customer->id,
        'method' => TopupMethod::ShamCash,
        'amount' => 50,
        'currency' => 'USD',
        'status' => TopupRequestStatus::Pending,
    ]);

    Livewire::actingAs($admin)
        ->test('pages::backend.topups.index')
        ->call('approveTopup', $topupRequest->id);

    $wallet->refresh();
    $topupRequest->refresh();

    $transaction = WalletTransaction::query()
        ->where('reference_type', TopupRequest::class)
        ->where('reference_id', $topupRequest->id)
        ->firstOrFail();

    expect((float) $wallet->balance)->toBe(50.0);
    expect($transaction->status)->toBe(WalletTransaction::STATUS_POSTED);
    expect($topupRequest->status)->toBe(TopupRequestStatus::Approved);
    expect($topupRequest->approved_by)->toBe($admin->id);
});
