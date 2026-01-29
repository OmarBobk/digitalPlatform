<?php

declare(strict_types=1);

namespace App\Actions\Fulfillments;

use App\Enums\FulfillmentLogLevel;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\User;

class CreateFulfillmentsForOrder
{
    /**
     * Create fulfillments for each order item once payment is completed.
     */
    public function handle(Order $order): void
    {
        if ($order->status !== OrderStatus::Paid) {
            return;
        }

        $appendLog = new AppendFulfillmentLog;

        $order->items()->get()->each(function ($item) use ($order, $appendLog): void {
            $fulfillment = Fulfillment::firstOrCreate(
                ['order_item_id' => $item->id],
                [
                    'order_id' => $order->id,
                    'provider' => 'manual',
                    'status' => FulfillmentStatus::Queued,
                    'attempts' => 0,
                ]
            );

            if ($fulfillment->wasRecentlyCreated) {
                $appendLog->handle($fulfillment, FulfillmentLogLevel::Info, 'Fulfillment queued');

                activity()
                    ->inLog('fulfillment')
                    ->event('fulfillment.queued')
                    ->performedOn($fulfillment)
                    ->causedBy(User::query()->find($order->user_id))
                    ->withProperties([
                        'fulfillment_id' => $fulfillment->id,
                        'order_id' => $order->id,
                        'order_item_id' => $item->id,
                        'provider' => $fulfillment->provider,
                        'status_to' => FulfillmentStatus::Queued->value,
                    ])
                    ->log('Fulfillment queued');
            }
        });
    }
}
