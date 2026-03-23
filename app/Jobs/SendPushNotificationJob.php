<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\PushLog;
use App\Services\FirebasePushService;
use App\Services\PushRateLimiter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

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
     * @param  array{title: string, body: string, sound: string, url: string, trace_id?: string}  $payload
     */
    public function __construct(
        public array $tokens,
        public array $payload,
        public ?string $notificationType = null,
        public ?string $notificationId = null,
        public ?string $traceId = null
    ) {
        $this->onQueue('push');
    }

    public function handle(FirebasePushService $service, PushRateLimiter $rateLimiter): void
    {
        if ($this->notificationId !== null && $this->alreadySent()) {
            return;
        }
        if ($this->notificationId !== null && $this->shouldSkipDedup()) {
            return;
        }

        $rateLimiter->acquire();

        $result = $service->sendToTokens($this->tokens, $this->payload);

        $status = $result['fail_count'] === 0 ? 'success' : ($result['success_count'] > 0 ? 'partial' : 'failed');

        try {
            PushLog::query()->create([
                'notification_type' => $this->notificationType,
                'notification_id' => $this->notificationId,
                'trace_id' => $this->traceId,
                'token_count' => count($this->tokens),
                'status' => $status,
                'error' => $result['last_error'],
            ]);
        } catch (\Throwable $e) {
            Log::warning('SendPushNotificationJob: PushLog create failed (push was sent)', [
                'notification_id' => $this->notificationId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** Skip if we already logged a send for this notification (idempotent retries). */
    private function alreadySent(): bool
    {
        return PushLog::query()
            ->where('notification_id', $this->notificationId)
            ->exists();
    }

    /** Skip if same notification was sent in the last 10 seconds (dedup rapid duplicates). */
    private function shouldSkipDedup(): bool
    {
        return PushLog::query()
            ->where('notification_id', $this->notificationId)
            ->where('created_at', '>=', now()->subSeconds(10))
            ->exists();
    }
}
