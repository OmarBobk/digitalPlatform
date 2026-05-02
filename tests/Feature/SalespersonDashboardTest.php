<?php

use App\Actions\Fortify\CreateNewUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

test('salesperson dashboard requires sales permission', function () {
    Permission::query()->firstOrCreate(['name' => 'view_referrals']);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('salesperson.dashboard'))
        ->assertNotFound();

    $user->givePermissionTo('view_referrals');

    $this->actingAs($user)
        ->get(route('salesperson.dashboard'))
        ->assertSuccessful()
        ->assertSee(__('messages.salesperson_dashboard'));
});

test('newly registered user is linked to referrer from referral cookie', function () {
    $referrer = User::factory()->create();

    $cookieName = (string) config('referral.cookie_name', 'karman_ref');
    $request = Request::create('/', 'POST', [], [$cookieName => $referrer->referral_code]);
    $this->app->instance('request', $request);

    $user = app(CreateNewUser::class)->create([
        'name' => 'Referred User',
        'username' => 'referreduser',
        'email' => 'referred@example.com',
        'preferred_currency' => 'USD',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'country_code' => '90',
        'timezone_detected' => 'Europe/Istanbul',
    ]);

    expect($user->referred_by_user_id)->toBe($referrer->id);
});
