<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Fulfillment;
use Illuminate\Support\Facades\Route;

class FulfillmentProcessFailedNotification extends BaseNotification
{
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
