<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin']);
    Role::firstOrCreate(['name' => 'customer']);
    Permission::firstOrCreate(['name' => 'manage_topups']);
});

it('allows admin to register push token', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $admin->givePermissionTo('manage_topups');

    $response = actingAs($admin)->postJson('/api/admin/push/register-token', [
        'fcm_token' => 'test-fcm-token-'.uniqid(),
        'device_name' => 'Chrome Android',
    ]);

    $response->assertSuccessful();
    $response->assertJson(['ok' => true]);
});

it('forbids non-admin from registering push token', function () {
    $user = User::factory()->create();
    $user->assignRole('customer');
    $user->givePermissionTo('manage_topups');

    $response = actingAs($user)->postJson('/api/admin/push/register-token', [
        'fcm_token' => 'test-fcm-token',
    ]);

    $response->assertForbidden();
});

it('requires authentication to register push token', function () {
    $response = postJson('/api/admin/push/register-token', [
        'fcm_token' => 'test-fcm-token',
    ]);

    $response->assertUnauthorized();
});
