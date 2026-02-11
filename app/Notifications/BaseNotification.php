<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;

abstract class BaseNotification extends Notification implements ShouldBroadcast
{
    /**
     * @param  class-string  $sourceType  Model class (e.g. TopupRequest::class)
     */
    public function __construct(
        protected string $sourceType,
        protected int $sourceId,
        protected string $title,
        protected string $message,
        protected ?string $url = null
    ) {}

    /**
     * Use broadcast channel only when a non-null driver is configured (e.g. reverb).
     * Avoids failed jobs when Reverb is not running or BROADCAST_CONNECTION=null.
     *
     * @return array<string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];
        if (config('broadcasting.default') !== 'null') {
            $channels[] = 'broadcast';
        }

        return $channels;
    }

    /**
     * Return empty so Laravel uses the notifiable's channel (private-App.Models.User.{id}).
     *
     * @return array<int, mixed>
     */
    public function broadcastOn(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'source_type' => $this->sourceType,
            'source_id' => $this->sourceId,
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
        ];
    }
}
