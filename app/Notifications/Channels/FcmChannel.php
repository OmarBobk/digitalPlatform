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

        $notificationType = get_class($notification);
        $eventId = method_exists($notification, 'getFcmDedupId') ? $notification->getFcmDedupId() : null;
        $notificationId = $eventId !== null ? $eventId.'.'.($notifiable->getKey() ?? '') : null;

        SendPushNotificationJob::dispatch($tokens, $payload, $notificationType, $notificationId);
    }
}
