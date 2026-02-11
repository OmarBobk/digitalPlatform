<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Route;

class PaymentFailedNotification extends BaseNotification
{
    public static function fromOrder(Order $order, string $reason): self
    {
        return new self(
            sourceType: Order::class,
            sourceId: $order->id,
            title: __('notifications.payment_failed_title'),
            message: __('notifications.payment_failed_message', [
                'order_number' => $order->order_number,
                'reason' => $reason,
            ]),
            url: Route::has('cart') ? route('cart') : null
        );
    }

    /**
     * Use when order is not available (e.g. after transaction rollback).
     */
    public static function forUser(User $user, string $reason, ?string $orderNumber = null): self
    {
        $message = $orderNumber !== null
            ? __('notifications.payment_failed_message', ['order_number' => $orderNumber, 'reason' => $reason])
            : __('notifications.payment_failed_message_no_order', ['reason' => $reason]);

        return new self(
            sourceType: User::class,
            sourceId: $user->id,
            title: __('notifications.payment_failed_title'),
            message: $message,
            url: Route::has('cart') ? route('cart') : null
        );
    }
}
