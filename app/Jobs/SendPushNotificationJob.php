<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\FirebasePushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendPushNotificationJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, string>  $tokens
     * @param  array{title: string, body: string, sound: string, url: string}  $payload
     */
    public function __construct(
        public array $tokens,
        public array $payload
    ) {
        $this->onQueue('push');
    }

    public function handle(FirebasePushService $service): void
    {
        $service->sendToTokens($this->tokens, $this->payload);
    }
}
