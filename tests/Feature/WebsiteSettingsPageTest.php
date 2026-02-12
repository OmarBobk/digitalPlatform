<?php

use App\Models\User;
use App\Models\WebsiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $adminRole->givePermissionTo(Permission::firstOrCreate(['name' => 'manage_users', 'guard_name' => 'web']));
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
        ->call('save')
        ->assertDispatched('website-settings-saved');

    $settings = WebsiteSetting::instance();
    expect($settings->contact_email)->toBe('contact@example.com');
    expect($settings->primary_phone)->toBe('+90 (555) 123-4567');
    expect($settings->secondary_phone)->toBe('+963 (11) 234-5678');
    expect($settings->prices_visible)->toBeTrue();
});
