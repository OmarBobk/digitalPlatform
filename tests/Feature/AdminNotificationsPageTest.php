<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $role = Role::firstOrCreate(['name' => 'admin']);
    $role->givePermissionTo(Permission::firstOrCreate(['name' => 'view_dashboard']));

    $this->admin = User::factory()->create();
    $this->admin->assignRole($role);
});

test('mark all as read clears unread notifications for the admin', function (): void {
    $this->admin->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\TopupRequestedNotification',
        'data' => ['title' => 'One', 'message' => 'M1'],
    ]);
    $this->admin->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\TopupRequestedNotification',
        'data' => ['title' => 'Two', 'message' => 'M2'],
    ]);

    expect($this->admin->unreadNotifications()->count())->toBe(2);

    Livewire::actingAs($this->admin)
        ->test('pages::backend.notifications.index')
        ->call('markAllAsRead');

    expect($this->admin->unreadNotifications()->count())->toBe(0)
        ->and($this->admin->notifications()->whereNotNull('read_at')->count())->toBe(2);
});

test('admin notifications page shows mark all as read when unread exist', function (): void {
    $this->admin->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\TopupRequestedNotification',
        'data' => ['title' => 'One', 'message' => 'M1'],
    ]);

    Livewire::actingAs($this->admin)
        ->test('pages::backend.notifications.index')
        ->assertSee(__('messages.mark_all_read'), false);
});

test('admin can open the notifications admin route', function (): void {
    $this->actingAs($this->admin)
        ->get(route('admin.notifications.index'))
        ->assertSuccessful()
        ->assertSeeLivewire('pages::backend.notifications.index');
});
