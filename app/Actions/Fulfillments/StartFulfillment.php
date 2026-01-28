<?php

declare(strict_types=1);

namespace App\Actions\Fulfillments;

use App\Enums\FulfillmentLogLevel;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderItemStatus;
use App\Models\Fulfillment;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;

class StartFulfillment
{
    public function handle(
        Fulfillment $fulfillment,
        string $actor = 'system',
        ?int $actorId = null,
        array $meta = []
    ): Fulfillment {
        return DB::transaction(function () use ($fulfillment, $actor, $actorId, $meta): Fulfillment {
            $lockedFulfillment = Fulfillment::query()
                ->whereKey($fulfillment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedFulfillment->status !== FulfillmentStatus::Queued) {
                return $lockedFulfillment;
            }

            $lockedFulfillment->fill([
                'status' => FulfillmentStatus::Processing,
                'processed_at' => $lockedFulfillment->processed_at ?? now(),
                'attempts' => $lockedFulfillment->attempts + 1,
                'last_error' => null,
            ])->save();

            OrderItem::query()
                ->whereKey($lockedFulfillment->order_item_id)
                ->update(['status' => OrderItemStatus::Processing]);

            app(AppendFulfillmentLog::class)->handle(
                $lockedFulfillment,
                FulfillmentLogLevel::Info,
                'Fulfillment started',
                $this->buildContext('start', $actor, $actorId, $meta)
            );

            return $lockedFulfillment->refresh();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function buildContext(string $action, string $actor, ?int $actorId, array $meta): array
    {
        return array_filter([
            'action' => $action,
            'actor' => $actor,
            'actor_id' => $actorId,
            'meta' => $meta !== [] ? $meta : null,
        ], fn ($value) => $value !== null);
    }
}
