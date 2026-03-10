<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('admin has install_pwa_app permission', function () {
    $permission = Permission::firstOrCreate(['name' => 'install_pwa_app']);
    $adminRole = Role::firstOrCreate(['name' => 'admin']);
    $adminRole->givePermissionTo($permission);

    $admin = User::factory()->create();
    $admin->assignRole($adminRole);

    expect($admin->can('install_pwa_app'))->toBeTrue();
});

test('customer does not have install_pwa_app permission', function () {
    $customerRole = Role::firstOrCreate(['name' => 'customer']);
    $customer = User::factory()->create();
    $customer->assignRole($customerRole);

    expect($customer->can('install_pwa_app'))->toBeFalse();
});
