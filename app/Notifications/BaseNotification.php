<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

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
        protected ?string $url = null,
        protected ?string $traceId = null
    ) {
        $this->traceId ??= (string) Str::uuid();
    }

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
            'trace_id' => $this->traceId,
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
        ];
    }

    /**
     * Unique id for FCM push deduplication (same event = same id).
     */
    public function getFcmDedupId(): string
    {
        return hash('sha256', get_class($this).$this->sourceType.((string) $this->sourceId));
    }

    public function getTraceId(): string
    {
        return (string) $this->traceId;
    }
}
