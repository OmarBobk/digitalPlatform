<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Fulfillment;
use Illuminate\Support\Facades\Route;

class FulfillmentCompletedNotification extends BaseNotification
{
    public static function fromFulfillment(Fulfillment $fulfillment): self
    {
        $fulfillment->loadMissing('order');
        $orderNumber = $fulfillment->order?->order_number;
        $url = Route::has('orders.show') && $orderNumber ? route('orders.show', $orderNumber) : null;

        return new self(
            sourceType: Fulfillment::class,
            sourceId: $fulfillment->id,
            title: __('notifications.fulfillment_completed_title'),
            message: __('notifications.fulfillment_completed_message', [
                'order_id' => $fulfillment->order_id,
            ]),
            url: $url
        );
    }
}
