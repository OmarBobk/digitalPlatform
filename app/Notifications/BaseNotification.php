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
        protected string $title = '',
        protected string $message = '',
        protected ?string $url = null,
        protected ?string $traceId = null,
        protected ?string $titleKey = null,
        protected array $titleParams = [],
        protected ?string $messageKey = null,
        protected array $messageParams = []
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
            'title' => $this->resolvedTitle($notifiable),
            'message' => $this->resolvedMessage($notifiable),
            'url' => $this->url,
        ];
    }

    protected function resolvedTitle(object $notifiable): string
    {
        if ($this->titleKey !== null && $this->titleKey !== '') {
            return $this->translateForNotifiable($notifiable, $this->titleKey, $this->titleParams);
        }

        return $this->title;
    }

    protected function resolvedMessage(object $notifiable): string
    {
        if ($this->messageKey !== null && $this->messageKey !== '') {
            return $this->translateForNotifiable($notifiable, $this->messageKey, $this->messageParams);
        }

        return $this->message;
    }

    protected function translateForNotifiable(object $notifiable, string $key, array $params = []): string
    {
        $locale = $this->resolveLocale($notifiable);
        $resolvedParams = [];

        foreach ($params as $paramKey => $paramValue) {
            if (is_string($paramValue) && str_starts_with($paramValue, 'notifications.')) {
                $resolvedParams[$paramKey] = trans($paramValue, [], $locale);
            } else {
                $resolvedParams[$paramKey] = $paramValue;
            }
        }

        return trans($key, $resolvedParams, $locale);
    }

    protected function resolveLocale(object $notifiable): string
    {
        if (method_exists($notifiable, 'preferredLocale')) {
            $locale = $notifiable->preferredLocale();

            if (is_string($locale) && $locale !== '') {
                return $locale;
            }
        }

        return app()->getLocale();
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
