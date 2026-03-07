<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Fulfillment;
use Illuminate\Support\Facades\Route;

class FulfillmentProcessFailedNotification extends BaseNotification
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
        $path = Route::has('fulfillments') ? parse_url(route('fulfillments'), PHP_URL_PATH) : '/fulfillments';

        return [
            'title' => __('notifications.fulfillment_process_failed_title'),
            'body' => $this->message,
            'sound' => '/sounds/fulfillment.mp3',
            'url' => $path ?: '/fulfillments',
        ];
    }

    public static function fromFulfillment(Fulfillment $fulfillment, string $errorMessage): self
    {
        return new self(
            sourceType: Fulfillment::class,
            sourceId: $fulfillment->id,
            title: __('notifications.fulfillment_process_failed_title'),
            message: __('notifications.fulfillment_process_failed_message', [
                'fulfillment_id' => $fulfillment->id,
                'order_id' => $fulfillment->order_id,
                'error' => $errorMessage,
            ]),
            url: Route::has('fulfillments') ? route('fulfillments') : null
        );
    }
}
