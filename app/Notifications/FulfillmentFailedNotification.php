<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Fulfillment;
use Illuminate\Support\Facades\Route;

class FulfillmentFailedNotification extends BaseNotification
{
    public static function fromFulfillment(Fulfillment $fulfillment, string $reason): self
    {
        $fulfillment->loadMissing('order');
        $orderNumber = $fulfillment->order?->order_number;
        $url = Route::has('orders.show') && $orderNumber ? route('orders.show', $orderNumber) : null;

        return new self(
            sourceType: Fulfillment::class,
            sourceId: $fulfillment->id,
            titleKey: 'notifications.fulfillment_failed_title',
            messageKey: 'notifications.fulfillment_failed_message',
            messageParams: [
                'order_id' => $fulfillment->order_id,
                'reason' => $reason,
            ],
            url: $url
        );
    }
}
