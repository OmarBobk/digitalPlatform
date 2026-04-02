<?php

use App\Models\User;
use App\Notifications\PaymentFailedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('language switch persists locale for authenticated user', function () {
    $user = User::factory()->create([
        'locale' => 'en',
    ]);

    $this->actingAs($user)
        ->from('/profile')
        ->get('/language/ar')
        ->assertRedirect('/profile');

    expect(session('locale'))->toBe('ar');
    expect($user->fresh()->locale)->toBe('ar');
});

test('notification payload is translated using notifiable locale', function () {
    app()->setLocale('en');

    $user = User::factory()->create([
        'locale' => 'ar',
    ]);

    $notification = PaymentFailedNotification::forUser($user, 'Provider timeout', 'ORD-2026-00001');
    $payload = $notification->toArray($user);

    expect($payload['title'])->toBe(trans('notifications.payment_failed_title', [], 'ar'));
    expect($payload['message'])->toBe(trans('notifications.payment_failed_message', [
        'order_number' => 'ORD-2026-00001',
        'reason' => 'Provider timeout',
    ], 'ar'));
});
