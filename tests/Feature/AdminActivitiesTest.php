<?php

use App\Enums\OrderStatus;
use App\Events\ActivityLogChanged;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $role = Role::firstOrCreate(['name' => 'admin']);
    $role->syncPermissions([
        Permission::firstOrCreate(['name' => 'view_activities']),
    ]);
});

test('admin can view activities page', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $order = Order::create([
        'user_id' => $admin->id,
        'order_number' => Order::temporaryOrderNumber(),
        'currency' => 'USD',
        'subtotal' => 10,
        'fee' => 0,
        'total' => 10,
        'status' => OrderStatus::Paid,
    ]);

    activity()
        ->inLog('orders')
        ->event('order.paid')
        ->performedOn($order)
        ->causedBy($admin)
        ->withProperties([
            'order_id' => $order->id,
            'amount' => $order->total,
            'currency' => $order->currency,
        ])
        ->log('Order paid');

    $this->actingAs($admin)
        ->get('/admin/activities')
        ->assertOk()
        ->assertSee('order.paid')
        ->assertSee('Order paid');
});

test('non-admin cannot access activities page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/activities')
        ->assertNotFound();
});

test('activity creation dispatches broadcast event', function () {
    Event::fake([ActivityLogChanged::class]);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $order = Order::create([
        'user_id' => $admin->id,
        'order_number' => Order::temporaryOrderNumber(),
        'currency' => 'USD',
        'subtotal' => 12,
        'fee' => 0,
        'total' => 12,
        'status' => OrderStatus::Paid,
    ]);

    $activity = activity()
        ->inLog('orders')
        ->event('order.paid')
        ->performedOn($order)
        ->causedBy($admin)
        ->log('Order paid');

    Event::assertDispatched(ActivityLogChanged::class, function (ActivityLogChanged $event) use ($activity): bool {
        return $event->activityId === $activity->id
            && $event->reason === 'created';
    });
});
