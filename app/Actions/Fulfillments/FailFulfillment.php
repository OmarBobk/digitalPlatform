<?php

declare(strict_types=1);

namespace App\Actions\Fulfillments;

use App\Enums\FulfillmentLogLevel;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderItemStatus;
use App\Models\Fulfillment;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;

class FailFulfillment
{
    public function handle(
        Fulfillment $fulfillment,
        string $reason,
        string $actor = 'system',
        ?int $actorId = null
    ): Fulfillment {
        return DB::transaction(function () use ($fulfillment, $reason, $actor, $actorId): Fulfillment {
            $lockedFulfillment = Fulfillment::query()
                ->whereKey($fulfillment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedFulfillment->status === FulfillmentStatus::Failed) {
                return $lockedFulfillment;
            }

            if ($lockedFulfillment->status === FulfillmentStatus::Completed) {
                return $lockedFulfillment;
            }

            if ($lockedFulfillment->status === FulfillmentStatus::Cancelled) {
                return $lockedFulfillment;
            }

            $lockedFulfillment->fill([
                'status' => FulfillmentStatus::Failed,
                'processed_at' => $lockedFulfillment->processed_at ?? now(),
                'last_error' => $reason,
            ])->save();

            OrderItem::query()
                ->whereKey($lockedFulfillment->order_item_id)
                ->update(['status' => OrderItemStatus::Failed]);

            app(AppendFulfillmentLog::class)->handle(
                $lockedFulfillment,
                FulfillmentLogLevel::Error,
                'Fulfillment failed',
                $this->buildContext('failed', $actor, $actorId, $reason)
            );

            return $lockedFulfillment->refresh();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function buildContext(string $action, string $actor, ?int $actorId, string $reason): array
    {
        return array_filter([
            'action' => $action,
            'actor' => $actor,
            'actor_id' => $actorId,
            'reason' => $reason,
        ], fn ($value) => $value !== null);
    }
}
