<?php

declare(strict_types=1);

namespace App\Actions\Fulfillments;

use App\Enums\FulfillmentLogLevel;
use App\Enums\FulfillmentStatus;
use App\Models\Fulfillment;
use App\Models\OrderItem;
use App\Models\User;
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

            $statusFrom = $lockedFulfillment->status;

            $lockedFulfillment->fill([
                'status' => FulfillmentStatus::Processing,
                'processed_at' => $lockedFulfillment->processed_at ?? now(),
                'attempts' => $lockedFulfillment->attempts + 1,
                'last_error' => null,
            ])->save();

            $orderItem = OrderItem::query()
                ->whereKey($lockedFulfillment->order_item_id)
                ->lockForUpdate()
                ->firstOrFail();
            $fulfillments = Fulfillment::query()
                ->where('order_item_id', $orderItem->id)
                ->lockForUpdate()
                ->get();
            $orderItem->syncStatusFromFulfillments($fulfillments);

            app(AppendFulfillmentLog::class)->handle(
                $lockedFulfillment,
                FulfillmentLogLevel::Info,
                'Fulfillment started',
                $this->buildContext('start', $actor, $actorId, $meta)
            );

            activity()
                ->inLog('fulfillment')
                ->event('fulfillment.processing')
                ->performedOn($lockedFulfillment)
                ->causedBy($actorId ? User::query()->find($actorId) : null)
                ->withProperties(array_filter([
                    'fulfillment_id' => $lockedFulfillment->id,
                    'order_id' => $lockedFulfillment->order_id,
                    'order_item_id' => $lockedFulfillment->order_item_id,
                    'provider' => $lockedFulfillment->provider,
                    'status_from' => $statusFrom->value,
                    'status_to' => FulfillmentStatus::Processing->value,
                    'attempts' => $lockedFulfillment->attempts,
                    'actor' => $actor,
                    'actor_id' => $actorId,
                ], fn ($value) => $value !== null && $value !== ''))
                ->log('Fulfillment processing');

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
