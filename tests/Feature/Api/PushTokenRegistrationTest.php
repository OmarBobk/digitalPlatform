<?php

use App\Models\AdminDevice;
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

it('reassigns token to current user when same fcm_token is registered by another admin', function () {
    $admin1 = User::factory()->create();
    $admin1->assignRole('admin');
    $admin1->givePermissionTo('manage_topups');
    $admin2 = User::factory()->create();
    $admin2->assignRole('admin');
    $admin2->givePermissionTo('manage_topups');

    $token = 'shared-fcm-token-'.uniqid();

    actingAs($admin1)->postJson('/api/admin/push/register-token', [
        'fcm_token' => $token,
        'device_name' => 'Admin1 Device',
    ])->assertSuccessful();

    $device = AdminDevice::query()->where('fcm_token', $token)->first();
    expect($device)->not->toBeNull()
        ->and($device->user_id)->toBe($admin1->id)
        ->and($device->device_name)->toBe('Admin1 Device');

    actingAs($admin2)->postJson('/api/admin/push/register-token', [
        'fcm_token' => $token,
        'device_name' => 'Admin2 Device',
    ])->assertSuccessful();

    $device->refresh();
    expect(AdminDevice::query()->where('fcm_token', $token)->count())->toBe(1)
        ->and($device->user_id)->toBe($admin2->id)
        ->and($device->device_name)->toBe('Admin2 Device');
});
