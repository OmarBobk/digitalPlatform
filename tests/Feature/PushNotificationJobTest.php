<?php

use App\Jobs\SendPushNotificationJob;
use App\Models\PushLog;
use App\Services\FirebasePushService;

use function Pest\Laravel\assertDatabaseCount;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('has retry and backoff configured', function () {
    $job = new SendPushNotificationJob(['t1'], ['title' => 'T', 'body' => 'B', 'sound' => 'default', 'url' => '/']);
    expect($job->tries)->toBe(5)
        ->and($job->backoff)->toBe([10, 30, 60, 120]);
});

it('sends push and logs to push_logs', function () {
    $this->mock(FirebasePushService::class, function ($mock) {
        $mock->shouldReceive('sendToTokens')
            ->once()
            ->andReturn(['success_count' => 1, 'fail_count' => 0, 'last_error' => null]);
    });

    $job = new SendPushNotificationJob(
        ['token1'],
        ['title' => 'Test', 'body' => 'Body', 'sound' => 'default', 'url' => '/'],
        'App\Notifications\SomeNotification',
        null
    );
    $job->handle(
        app(FirebasePushService::class),
        app(\App\Services\PushRateLimiter::class)
    );

    assertDatabaseCount('push_logs', 1);
    $log = PushLog::query()->first();
    expect($log->notification_type)->toBe('App\Notifications\SomeNotification')
        ->and($log->token_count)->toBe(1)
        ->and($log->status)->toBe('success')
        ->and($log->error)->toBeNull();
});

it('skips send when same notification_id was processed within 10 seconds', function () {
    PushLog::query()->create([
        'notification_type' => 'App\Notifications\SomeNotification',
        'notification_id' => 'dedup-hash-123',
        'token_count' => 1,
        'status' => 'success',
        'error' => null,
    ]);

    $this->mock(FirebasePushService::class, function ($mock) {
        $mock->shouldNotReceive('sendToTokens');
    });

    $job = new SendPushNotificationJob(
        ['token1'],
        ['title' => 'Test', 'body' => 'Body', 'sound' => 'default', 'url' => '/'],
        'App\Notifications\SomeNotification',
        'dedup-hash-123'
    );
    $job->handle(
        app(FirebasePushService::class),
        app(\App\Services\PushRateLimiter::class)
    );

    assertDatabaseCount('push_logs', 1);
});

it('logs failed status when all tokens fail', function () {
    $this->mock(FirebasePushService::class, function ($mock) {
        $mock->shouldReceive('sendToTokens')
            ->once()
            ->andReturn(['success_count' => 0, 'fail_count' => 1, 'last_error' => 'UNREGISTERED']);
    });

    $job = new SendPushNotificationJob(
        ['bad-token'],
        ['title' => 'T', 'body' => 'B', 'sound' => 'default', 'url' => '/'],
        null,
        null
    );
    $job->handle(
        app(FirebasePushService::class),
        app(\App\Services\PushRateLimiter::class)
    );

    $log = PushLog::query()->first();
    expect($log->status)->toBe('failed')
        ->and($log->error)->toBe('UNREGISTERED');
});

it('stores long notification ids when push log is created', function () {
    $this->mock(FirebasePushService::class, function ($mock) {
        $mock->shouldReceive('sendToTokens')
            ->once()
            ->andReturn(['success_count' => 1, 'fail_count' => 0, 'last_error' => null]);
    });

    $notificationId = hash('sha256', 'event-key').'.18';

    $job = new SendPushNotificationJob(
        ['token1'],
        ['title' => 'Test', 'body' => 'Body', 'sound' => 'default', 'url' => '/'],
        'App\Notifications\SomeNotification',
        $notificationId
    );
    $job->handle(
        app(FirebasePushService::class),
        app(\App\Services\PushRateLimiter::class)
    );

    $log = PushLog::query()->first();
    expect($log)->not()->toBeNull()
        ->and($log->notification_id)->toBe($notificationId)
        ->and(strlen((string) $log->notification_id))->toBeGreaterThan(64);
});

it('removes invalid token from admin_devices when FCM returns UNREGISTERED', function () {
    \Illuminate\Support\Facades\Cache::put('firebase_fcm_access_token', 'fake-token', 60);
    config(['firebase.project_id' => 'test-project']);
    $device = \App\Models\AdminDevice::factory()->create(['fcm_token' => 'invalid-fcm-token-123']);

    \Illuminate\Support\Facades\Http::fake([
        'https://fcm.googleapis.com/*' => \Illuminate\Support\Facades\Http::response([
            'error' => [
                'message' => 'Requested entity was not found.',
                'details' => [['errorCode' => 'UNREGISTERED']],
            ],
        ], 400),
    ]);

    $service = app(FirebasePushService::class);
    $service->sendToTokens(
        ['invalid-fcm-token-123'],
        ['title' => 'T', 'body' => 'B', 'sound' => 'default', 'url' => '/']
    );

    expect(\App\Models\AdminDevice::query()->where('fcm_token', 'invalid-fcm-token-123')->exists())->toBeFalse();
});
