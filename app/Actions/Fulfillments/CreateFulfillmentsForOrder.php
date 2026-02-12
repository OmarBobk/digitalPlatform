<?php

declare(strict_types=1);

namespace App\Actions\Fulfillments;

use App\Enums\FulfillmentLogLevel;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Events\FulfillmentListChanged;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\SystemEventService;
use Illuminate\Support\Facades\DB;

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
            DB::transaction(function () use ($order, $appendLog, $item): void {
                $lockedItem = OrderItem::query()
                    ->whereKey($item->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $quantity = max(0, (int) $lockedItem->quantity);
                $requirementsPayload = $lockedItem->requirements_payload;
                $meta = $requirementsPayload !== null && $requirementsPayload !== []
                    ? ['requirements_payload' => $requirementsPayload]
                    : [];

                $existingFulfillments = Fulfillment::query()
                    ->where('order_item_id', $lockedItem->id)
                    ->lockForUpdate()
                    ->get();

                if ($meta !== []) {
                    foreach ($existingFulfillments as $existing) {
                        $currentMeta = $existing->meta ?? [];

                        if (! array_key_exists('requirements_payload', $currentMeta)) {
                            $existing->update([
                                'meta' => array_merge($currentMeta, $meta),
                            ]);
                        }
                    }
                }

                $needed = max(0, $quantity - $existingFulfillments->count());

                for ($i = 0; $i < $needed; $i++) {
                    $fulfillment = Fulfillment::create([
                        'order_id' => $order->id,
                        'order_item_id' => $lockedItem->id,
                        'provider' => 'manual',
                        'status' => FulfillmentStatus::Queued,
                        'attempts' => 0,
                        'meta' => $meta,
                    ]);

                    if ($requirementsPayload !== null && $requirementsPayload !== []) {
                        $appendLog->handle($fulfillment, FulfillmentLogLevel::Info, 'Requirements captured', [
                            'action' => 'requirements_captured',
                        ]);
                    }

                    $appendLog->handle($fulfillment, FulfillmentLogLevel::Info, 'Fulfillment queued');

                    $fulfillmentId = $fulfillment->id;
                    $orderId = $order->id;
                    DB::afterCommit(static function () use ($fulfillmentId, $orderId): void {
                        event(new FulfillmentListChanged($fulfillmentId, 'created'));
                        $fulfillment = Fulfillment::query()->find($fulfillmentId);
                        $order = Order::query()->find($orderId);
                        if ($fulfillment !== null && $order !== null) {
                            $orderUser = User::query()->find($order->user_id);
                            app(SystemEventService::class)->record(
                                'fulfillment.created',
                                $fulfillment,
                                $orderUser,
                                [
                                    'order_id' => $order->id,
                                    'order_item_id' => $fulfillment->order_item_id,
                                    'provider' => $fulfillment->provider,
                                ],
                                'info',
                                false,
                            );
                        }
                    });

                    activity()
                        ->inLog('fulfillment')
                        ->event('fulfillment.queued')
                        ->performedOn($fulfillment)
                        ->causedBy(User::query()->find($order->user_id))
                        ->withProperties([
                            'fulfillment_id' => $fulfillment->id,
                            'order_id' => $order->id,
                            'order_item_id' => $lockedItem->id,
                            'provider' => $fulfillment->provider,
                            'status_to' => FulfillmentStatus::Queued->value,
                        ])
                        ->log('Fulfillment queued');
                }
            });
        });
    }
}
