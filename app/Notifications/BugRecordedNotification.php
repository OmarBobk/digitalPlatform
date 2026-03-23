<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Bug;
use Illuminate\Support\Facades\Route;

class BugRecordedNotification extends BaseNotification
{
    public function via(object $notifiable): array
    {
        $channels = ['database'];
        if (config('broadcasting.default') !== 'null') {
            $channels[] = 'broadcast';
        }
        $channels[] = 'fcm';

        return $channels;
    }

    /**
     * @return array{title: string, body: string, sound: string, url: string}
     */
    public function toFcm(object $notifiable): array
    {
        $path = Route::has('admin.bugs.index')
            ? parse_url(route('admin.bugs.show', $this->sourceId), PHP_URL_PATH)
            : '/admin/bugs';

        return [
            'title' => __('notifications.bug_recorded_title'),
            'body' => $this->message,
            'sound' => '/sounds/fulfillment.mp3',
            'url' => $path ?: '/admin/bugs',
        ];
    }

    public static function fromBug(Bug $bug): self
    {
        $scenarioLabel = str_replace('_', ' ', (string) $bug->scenario);

        return new self(
            sourceType: Bug::class,
            sourceId: $bug->id,
            title: __('notifications.bug_recorded_title'),
            message: __('notifications.bug_recorded_message', [
                'id' => $bug->id,
                'scenario' => $scenarioLabel,
                'severity' => $bug->severity,
            ]),
            url: Route::has('admin.bugs.show') ? route('admin.bugs.show', $bug) : null,
            traceId: $bug->trace_id !== null ? (string) $bug->trace_id : null,
        );
    }
}
