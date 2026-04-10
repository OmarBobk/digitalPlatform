<?php

use App\Models\User;
use App\Models\WebsiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $adminRole->givePermissionTo(Permission::firstOrCreate(['name' => 'manage_users', 'guard_name' => 'web']));
    $adminRole->givePermissionTo(Permission::firstOrCreate(['name' => 'view_dashboard', 'guard_name' => 'web']));
});

test('guest cannot access website settings page', function () {
    $this->get(route('admin.website-settings'))
        ->assertRedirect();
});

test('non-admin cannot access website settings page', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('manage_users');

    $this->actingAs($user)
        ->get(route('admin.website-settings'))
        ->assertRedirect();
});

test('admin can view website settings page', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('admin.website-settings'))
        ->assertOk()
        ->assertSee(__('messages.website_settings'))
        ->assertSee(__('messages.website_settings_intro'));
});

test('admin can save website settings', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    Livewire::actingAs($admin)
        ->test('pages::backend.website-settings.index')
        ->set('contactEmail', 'contact@example.com')
        ->set('primaryCountryCode', '+90')
        ->set('primaryPhone', '(555) 123-4567')
        ->set('secondaryCountryCode', '+963')
        ->set('secondaryPhone', '(11) 234-5678')
        ->set('pricesVisible', true)
        ->set('usdTryRate', '39.125000')
        ->call('save')
        ->assertDispatched('website-settings-saved');

    $settings = WebsiteSetting::instance();
    expect($settings->contact_email)->toBe('contact@example.com');
    expect($settings->primary_phone)->toBe('+90 (555) 123-4567');
    expect($settings->secondary_phone)->toBe('+963 (11) 234-5678');
    expect($settings->prices_visible)->toBeTrue();
    expect((float) $settings->usd_try_rate)->toBe(39.125);
    expect($settings->usd_try_rate_updated_at)->not()->toBeNull();
});

test('admin can fetch usd try rate into form', function () {
    Http::fake([
        'https://open.er-api.com/*' => Http::response([
            'rates' => [
                'TRY' => 40.55,
            ],
        ], 200),
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    Livewire::actingAs($admin)
        ->test('pages::backend.website-settings.index')
        ->call('fetchUsdTryRate')
        ->assertSet('usdTryRate', '40.550000')
        ->assertHasNoErrors();
});

test('admin dashboard sidebar shows configured usd try rate', function () {
    WebsiteSetting::instance()->update([
        'usd_try_rate' => 41.25,
        'usd_try_rate_updated_at' => now(),
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-test="admin-sidebar-usd-try-rate"', false)
        ->assertSee('41.250000', false);
});

test('admin sees usd try rate on storefront header when configured', function () {
    WebsiteSetting::instance()->update([
        'usd_try_rate' => 33.5,
        'usd_try_rate_updated_at' => now(),
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('home'))
        ->assertOk()
        ->assertSee('data-test="frontend-header-admin-usd-try-rate"', false)
        ->assertSee('33.5000', false);
});
