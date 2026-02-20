<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
});

test('guest cannot access profile page', function () {
    $this->get(route('profile'))
        ->assertRedirect();
});

test('authenticated user can view profile page and sees their name', function () {
    $user = User::factory()->create(['name' => 'Jane Doe']);

    $this->actingAs($user)
        ->get(route('profile'))
        ->assertOk()
        ->assertSee('Jane Doe')
        ->assertSee($user->username)
        ->assertSee($user->email)
        ->assertSee(__('main.profile'))
        ->assertSee(__('messages.edit'));
});

test('profile page shows wallet balance and quick links', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('profile'))
        ->assertOk()
        ->assertSee(__('main.wallet'))
        ->assertSee(__('main.my_orders'))
        ->assertSee(__('messages.notifications'));
});

test('guest cannot access profile edit page', function () {
    $this->get(route('profile.edit-information'))
        ->assertRedirect();
});

test('authenticated user can view and submit profile edit page', function () {

    $user = User::factory()->create([
        'name' => 'Old Name',
        'username' => 'testuser99',
        'country_code' => '+90',
        'timezone' => \App\Enums\Timezone::Turkey,
    ]);

    $this->actingAs($user)
        ->get(route('profile.edit-information'))
        ->assertOk()
        ->assertSee(__('messages.update_your_profile_information'))
        ->assertSee('Old Name');

    Livewire::actingAs($user)
        ->test('pages::frontend.profile-edit')
        ->set('name', 'New Name')
        ->set('username', 'testuser99')
        ->set('email', $user->email)
        ->set('phone', '5551234567')
        ->set('country_code', '+90')
        ->set('timezone', \App\Enums\Timezone::Turkey->value)
        ->call('updateProfileInformation')
        ->assertHasNoErrors()
        ->assertRedirect(route('profile'));

    $user->refresh();
    expect($user->name)->toBe('New Name');
});
