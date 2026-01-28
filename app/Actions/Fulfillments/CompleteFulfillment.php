<?php

declare(strict_types=1);

namespace App\Actions\Fulfillments;

use App\Enums\FulfillmentLogLevel;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderItemStatus;
use App\Models\Fulfillment;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;

class CompleteFulfillment
{
    public function handle(
        Fulfillment $fulfillment,
        ?array $deliveredPayload = null,
        string $actor = 'system',
        ?int $actorId = null
    ): Fulfillment {
        return DB::transaction(function () use ($fulfillment, $deliveredPayload, $actor, $actorId): Fulfillment {
            $lockedFulfillment = Fulfillment::query()
                ->whereKey($fulfillment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedFulfillment->status === FulfillmentStatus::Completed) {
                return $lockedFulfillment;
            }

            if ($lockedFulfillment->status === FulfillmentStatus::Cancelled) {
                return $lockedFulfillment;
            }

            $meta = $lockedFulfillment->meta ?? [];
            $meta['delivered_payload'] = $deliveredPayload;

            $lockedFulfillment->fill([
                'status' => FulfillmentStatus::Completed,
                'processed_at' => $lockedFulfillment->processed_at ?? now(),
                'completed_at' => $lockedFulfillment->completed_at ?? now(),
                'last_error' => null,
                'meta' => $meta,
            ])->save();

            OrderItem::query()
                ->whereKey($lockedFulfillment->order_item_id)
                ->update(['status' => OrderItemStatus::Fulfilled]);

            app(AppendFulfillmentLog::class)->handle(
                $lockedFulfillment,
                FulfillmentLogLevel::Info,
                'Fulfillment completed',
                $this->buildContext('completed', $actor, $actorId)
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
