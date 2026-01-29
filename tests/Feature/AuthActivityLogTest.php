<?php

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\LoginResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

test('user login and logout are logged', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    app(LoginResponse::class)->toResponse(request());

    expect(Activity::query()
        ->where('event', 'user.login')
        ->where('log_name', 'admin')
        ->where('subject_type', User::class)
        ->where('subject_id', $user->id)
        ->exists()
    )->toBeTrue();

    $this->post(route('logout'));

    expect(Activity::query()
        ->where('event', 'user.logout')
        ->where('log_name', 'admin')
        ->where('subject_type', User::class)
        ->where('subject_id', $user->id)
        ->exists()
    )->toBeTrue();
});

test('user registration is logged', function () {
    $action = app(CreateNewUser::class);

    $user = $action->create([
        'name' => 'Test User',
        'username' => 'test_user_1',
        'email' => 'test-user@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    expect(Activity::query()
        ->where('event', 'user.registered')
        ->where('log_name', 'admin')
        ->where('subject_type', User::class)
        ->where('subject_id', $user->id)
        ->exists()
    )->toBeTrue();
});
