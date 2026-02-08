<?php

use App\Enums\WalletTransactionDirection;
use App\Enums\WalletTransactionType;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('user without manage_topups cannot access customer funds page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('customer-funds'))
        ->assertNotFound();
});

test('user with manage_topups can view customer funds page', function () {
    $permission = Permission::firstOrCreate(['name' => 'manage_topups']);
    $role = Role::firstOrCreate(['name' => 'admin']);
    $role->givePermissionTo($permission);

    $admin = User::factory()->create();
    $admin->assignRole($role);

    $this->actingAs($admin)
        ->get(route('customer-funds'))
        ->assertOk()
        ->assertSee(__('messages.customer_funds'))
        ->assertSee(__('messages.total_customer_liability'));
});

test('customer funds page shows total liability and customer breakdown', function () {
    $permission = Permission::firstOrCreate(['name' => 'manage_topups']);
    $role = Role::firstOrCreate(['name' => 'admin']);
    $role->givePermissionTo($permission);

    $admin = User::factory()->create();
    $admin->assignRole($role);

    $customer1 = User::factory()->create(['name' => 'Alice', 'email' => 'alice@test.com']);
    $customer2 = User::factory()->create(['name' => 'Bob', 'email' => 'bob@test.com']);

    $wallet1 = Wallet::forUser($customer1);
    $wallet1->update(['balance' => 50]);

    $wallet2 = Wallet::forUser($customer2);
    $wallet2->update(['balance' => 30]);

    $this->actingAs($admin)
        ->get(route('customer-funds'))
        ->assertOk()
        ->assertSee('80.00')
        ->assertSee('Alice')
        ->assertSee('alice@test.com')
        ->assertSee('50.00')
        ->assertSee('Bob')
        ->assertSee('bob@test.com')
        ->assertSee('30.00');
});

test('customer funds detail modal shows balance breakdown', function () {
    $permission = Permission::firstOrCreate(['name' => 'manage_topups']);
    $role = Role::firstOrCreate(['name' => 'admin']);
    $role->givePermissionTo($permission);

    $admin = User::factory()->create();
    $admin->assignRole($role);

    $customer = User::factory()->create(['name' => 'Alice', 'email' => 'alice@test.com']);
    $wallet = Wallet::forUser($customer);
    $wallet->update(['balance' => 80]);

    WalletTransaction::create([
        'wallet_id' => $wallet->id,
        'type' => WalletTransactionType::Topup,
        'direction' => WalletTransactionDirection::Credit,
        'amount' => 100,
        'status' => WalletTransaction::STATUS_POSTED,
    ]);
    WalletTransaction::create([
        'wallet_id' => $wallet->id,
        'type' => WalletTransactionType::Purchase,
        'direction' => WalletTransactionDirection::Debit,
        'amount' => 20,
        'status' => WalletTransaction::STATUS_POSTED,
    ]);

    Livewire::actingAs($admin)
        ->test('pages::backend.customer-funds.index')
        ->call('openDetailModal', $wallet->id)
        ->assertSet('showDetailModal', true)
        ->assertSet('selectedWalletId', $wallet->id)
        ->assertSee('Alice')
        ->assertSee('80.00')
        ->assertSee(__('messages.wallet_transaction_type_topup'))
        ->assertSee(__('messages.wallet_transaction_type_purchase'))
        ->assertSee('100.00')
        ->assertSee('20.00');
});
