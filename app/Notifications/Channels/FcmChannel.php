<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Jobs\SendPushNotificationJob;
use Illuminate\Notifications\Notification;

class FcmChannel
{
    /**
     * Send the notification to the notifiable's FCM tokens via a queued job.
     */
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toFcm')) {
            return;
        }

        $tokens = $notifiable->adminDevices()->pluck('fcm_token')->all();
        if ($tokens === []) {
            return;
        }

        $payload = $notification->toFcm($notifiable);
        if (! is_array($payload)) {
            return;
        }

        SendPushNotificationJob::dispatch($tokens, $payload);
    }
}
