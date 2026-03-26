<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Support\Facades\Route;

class OrderPriceFlooredNotification extends BaseNotification
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
        $path = Route::has('admin.orders.show')
            ? parse_url(route('admin.orders.show', $this->sourceId), PHP_URL_PATH)
            : '/admin/orders';

        return [
            'title' => __('notifications.order_price_floored_title'),
            'body' => $this->message,
            'sound' => '/sounds/fulfillment.mp3',
            'url' => $path ?: '/admin/orders',
        ];
    }

    public static function fromOrder(Order $order, int $flooredItemsCount): self
    {
        return new self(
            sourceType: Order::class,
            sourceId: $order->id,
            title: __('notifications.order_price_floored_title'),
            message: __('notifications.order_price_floored_message', [
                'order_number' => $order->order_number,
                'count' => $flooredItemsCount,
            ]),
            url: Route::has('admin.orders.show') ? route('admin.orders.show', $order) : null
        );
    }
}
