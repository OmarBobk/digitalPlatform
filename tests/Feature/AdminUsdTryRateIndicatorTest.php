<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\WebsiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $adminRole->givePermissionTo(Permission::firstOrCreate(['name' => 'view_dashboard', 'guard_name' => 'web']));
});

test('admin sees usd try rate on dashboard sidebar and storefront header', function (): void {
    WebsiteSetting::query()->delete();
    WebsiteSetting::query()->create([
        'contact_email' => null,
        'primary_phone' => null,
        'secondary_phone' => null,
        'prices_visible' => true,
        'usd_try_rate' => 42.375,
        'usd_try_rate_updated_at' => now(),
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-test="admin-usd-try-rate-sidebar"', false)
        ->assertSee('42.375000', false)
        ->assertSee(__('messages.admin_usd_try_rate_heading'), false);

    $this->actingAs($admin)
        ->get(route('home'))
        ->assertOk()
        ->assertSee('data-test="admin-usd-try-rate-storefront"', false)
        ->assertSee('42.375000', false)
        ->assertSee(__('messages.admin_usd_try_rate_short'), false);
});

test('customer does not see admin usd try rate on storefront', function (): void {
    WebsiteSetting::query()->delete();
    WebsiteSetting::query()->create([
        'contact_email' => null,
        'primary_phone' => null,
        'secondary_phone' => null,
        'prices_visible' => true,
        'usd_try_rate' => 99,
        'usd_try_rate_updated_at' => now(),
    ]);

    $customer = User::factory()->create();

    $this->actingAs($customer)
        ->get(route('home'))
        ->assertOk()
        ->assertDontSee('data-test="admin-usd-try-rate-storefront"', false);
});

test('guest does not see admin usd try rate on storefront', function (): void {
    WebsiteSetting::query()->delete();
    WebsiteSetting::query()->create([
        'contact_email' => null,
        'primary_phone' => null,
        'secondary_phone' => null,
        'prices_visible' => true,
        'usd_try_rate' => 50,
        'usd_try_rate_updated_at' => now(),
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('data-test="admin-usd-try-rate-storefront"', false);
});
