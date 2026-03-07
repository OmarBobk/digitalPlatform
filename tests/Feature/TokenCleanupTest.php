<?php

use App\Models\AdminDevice;
use App\Models\User;

use function Pest\Laravel\artisan;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('deletes devices not seen for 90 days', function () {
    $user = User::factory()->create();
    $old = AdminDevice::factory()->create([
        'user_id' => $user->id,
        'last_seen_at' => now()->subDays(91),
    ]);
    $recent = AdminDevice::factory()->create([
        'user_id' => $user->id,
        'last_seen_at' => now()->subDays(30),
    ]);

    artisan('push:cleanup');

    expect(AdminDevice::query()->find($old->id))->toBeNull()
        ->and(AdminDevice::query()->find($recent->id))->not->toBeNull();
});

it('dry run does not delete stale devices', function () {
    $user = User::factory()->create();
    $old = AdminDevice::factory()->create([
        'user_id' => $user->id,
        'last_seen_at' => now()->subDays(91),
    ]);

    artisan('push:cleanup', ['--dry-run' => true]);

    expect(AdminDevice::query()->find($old->id))->not->toBeNull();
});

it('deletes devices with null last_seen_at when created_at is older than 90 days', function () {
    $user = User::factory()->create();
    $veryOld = AdminDevice::factory()->create([
        'user_id' => $user->id,
        'last_seen_at' => null,
        'created_at' => now()->subDays(100),
    ]);

    artisan('push:cleanup');

    expect(AdminDevice::query()->find($veryOld->id))->toBeNull();
});
