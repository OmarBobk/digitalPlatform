<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\PushLog;
use App\Services\FirebasePushService;
use App\Services\PushRateLimiter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendPushNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 60, 120];

    /**
     * @param  array<int, string>  $tokens
     * @param  array{title: string, body: string, sound: string, url: string}  $payload
     */
    public function __construct(
        public array $tokens,
        public array $payload,
        public ?string $notificationType = null,
        public ?string $notificationId = null
    ) {
        $this->onQueue('push');
    }

    public function handle(FirebasePushService $service, PushRateLimiter $rateLimiter): void
    {
        if ($this->notificationId !== null && $this->shouldSkipDedup()) {
            return;
        }

        $rateLimiter->acquire();

        $result = $service->sendToTokens($this->tokens, $this->payload);

        $status = $result['fail_count'] === 0 ? 'success' : ($result['success_count'] > 0 ? 'partial' : 'failed');

        PushLog::query()->create([
            'notification_type' => $this->notificationType,
            'notification_id' => $this->notificationId,
            'token_count' => count($this->tokens),
            'status' => $status,
            'error' => $result['last_error'],
        ]);
    }

    private function shouldSkipDedup(): bool
    {
        return PushLog::query()
            ->where('notification_id', $this->notificationId)
            ->where('created_at', '>=', now()->subSeconds(10))
            ->exists();
    }
}
