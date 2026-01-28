<?php

declare(strict_types=1);

namespace App\Actions\Fulfillments;

use App\Enums\FulfillmentLogLevel;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\WalletTransactionType;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

class RetryFulfillment
{
    public function handle(
        Fulfillment $fulfillment,
        string $actor = 'system',
        ?int $actorId = null
    ): Fulfillment {
        return DB::transaction(function () use ($fulfillment, $actor, $actorId): Fulfillment {
            $lockedFulfillment = Fulfillment::query()
                ->whereKey($fulfillment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedFulfillment->status !== FulfillmentStatus::Failed) {
                return $lockedFulfillment;
            }

            $orderItem = OrderItem::query()
                ->whereKey($lockedFulfillment->order_item_id)
                ->lockForUpdate()
                ->firstOrFail();

            $order = Order::query()
                ->whereKey($orderItem->order_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->status === OrderStatus::Refunded) {
                return $lockedFulfillment;
            }

            $idempotencyKey = 'refund:order:'.$order->id;

            $hasRefund = WalletTransaction::query()
                ->where('type', WalletTransactionType::Refund->value)
                ->whereIn('status', [WalletTransaction::STATUS_PENDING, WalletTransaction::STATUS_POSTED])
                ->where(function ($query) use ($orderItem, $order, $idempotencyKey): void {
                    $query->where(function ($subQuery) use ($orderItem): void {
                        $subQuery->where('reference_type', OrderItem::class)
                            ->where('reference_id', $orderItem->id);
                    })->orWhere(function ($subQuery) use ($order): void {
                        $subQuery->where('reference_type', Order::class)
                            ->where('reference_id', $order->id);
                    })->orWhere('idempotency_key', $idempotencyKey);
                })
                ->lockForUpdate()
                ->exists();

            if ($hasRefund) {
                return $lockedFulfillment;
            }

            $retryCount = (int) data_get($lockedFulfillment->meta, 'retry_count', 0) + 1;

            $lockedFulfillment->fill([
                'status' => FulfillmentStatus::Queued,
                'last_error' => null,
                'processed_at' => null,
                'completed_at' => null,
                'meta' => array_merge($lockedFulfillment->meta ?? [], [
                    'retry_count' => $retryCount,
                    'last_retry_at' => now()->toIso8601String(),
                    'last_retry_by' => $actorId,
                    'last_retry_actor' => $actor,
                ]),
            ])->save();

            $orderItem->update(['status' => OrderItemStatus::Pending]);

            app(AppendFulfillmentLog::class)->handle(
                $lockedFulfillment,
                FulfillmentLogLevel::Info,
                'Fulfillment queued',
                $this->buildContext('retry_requested', $actor, $actorId)
            );

            return $lockedFulfillment->refresh();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function buildContext(string $action, string $actor, ?int $actorId): array
    {
        return array_filter([
            'action' => $action,
            'actor' => $actor,
            'actor_id' => $actorId,
        ], fn ($value) => $value !== null);
    }
}
