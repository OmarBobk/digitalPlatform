<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('guests are redirected to the login page', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

test('non-admin users receive a not found response', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/dashboard')->assertNotFound();
});

test('admin users can visit the dashboard', function () {
    $role = Role::firstOrCreate(['name' => 'admin']);
    $admin = User::factory()->create();
    $admin->assignRole($role);

    $this->actingAs($admin);

    $this->get('/dashboard')->assertOk();
});
