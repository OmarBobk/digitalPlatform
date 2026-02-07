<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $role = Role::firstOrCreate(['name' => 'admin']);
    $role->syncPermissions([
        Permission::firstOrCreate(['name' => 'manage_products']),
    ]);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
});

test('pricing rules page renders for user with manage_products', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $this->actingAs($user)
        ->get(route('pricing-rules'))
        ->assertOk()
        ->assertSee(__('messages.pricing_rules'))
        ->assertSee('data-test="pricing-rules-page"', false);
});

test('pricing rules page returns 404 when user has no backend permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('pricing-rules'))
        ->assertNotFound();
});

test('pricing rules page is forbidden for user with backend but without manage_products', function () {
    $role = Role::firstOrCreate(['name' => 'salesperson']);
    $role->syncPermissions([
        Permission::firstOrCreate(['name' => 'view_orders']),
    ]);
    $user = User::factory()->create();
    $user->assignRole('salesperson');

    $this->actingAs($user)
        ->get(route('pricing-rules'))
        ->assertForbidden();
});
