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
            titleKey: 'notifications.payment_failed_title',
            messageKey: 'notifications.payment_failed_message',
            messageParams: [
                'order_number' => $order->order_number,
                'reason' => $reason,
            ],
            url: Route::has('cart') ? route('cart') : null
        );
    }

    /**
     * Use when order is not available (e.g. after transaction rollback).
     */
    public static function forUser(User $user, string $reason, ?string $orderNumber = null): self
    {
        return new self(
            sourceType: User::class,
            sourceId: $user->id,
            titleKey: 'notifications.payment_failed_title',
            messageKey: $orderNumber !== null
                ? 'notifications.payment_failed_message'
                : 'notifications.payment_failed_message_no_order',
            messageParams: $orderNumber !== null
                ? ['order_number' => $orderNumber, 'reason' => $reason]
                : ['reason' => $reason],
            url: Route::has('cart') ? route('cart') : null
        );
    }
}
