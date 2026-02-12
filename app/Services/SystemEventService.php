<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SystemEventSeverity;
use App\Events\SystemEventCreated;
use App\Jobs\PersistSystemEventJob;
use App\Models\SystemEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Centralized system event recording. Never starts or wraps DB::transaction();
 * caller owns atomicity. Financial events must be called inside an existing transaction.
 *
 * Severity discipline: default info; financial events info; reconciliation drift warning; system failure critical.
 */
class SystemEventService
{
    /**
     * Record a system event. Financial events are inserted in the current transaction;
     * informational events are persisted via an idempotent job after commit.
     * This method never starts or wraps a transaction.
     * Financial broadcast uses DB::afterCommit() so it fires only after commit.
     *
     * Async idempotency uses structured keys (e.g. order.created:Order:123) so meta
     * timestamps/random IDs do not break duplicate protection.
     *
     * @param  array<string, mixed>  $meta
     * @param  string|null  $idempotencySuffix  Optional suffix for events that can repeat per entity (e.g. tier.upgraded:User:1:gold)
     *
     * @throws \RuntimeException when isFinancial is true and caller is not inside a DB transaction
     */
    public function record(
        string $eventType,
        ?Model $entity = null,
        ?Model $actor = null,
        array $meta = [],
        SystemEventSeverity|string $severity = SystemEventSeverity::Info,
        bool $isFinancial = false,
        ?string $idempotencySuffix = null,
    ): ?SystemEvent {
        $severityValue = $severity instanceof SystemEventSeverity ? $severity->value : $severity;
        $entityType = $entity !== null ? $entity->getMorphClass() : null;
        $entityId = $entity !== null ? (int) $entity->getKey() : null;
        $actorType = $actor !== null ? $actor->getMorphClass() : null;
        $actorId = $actor !== null ? (int) $actor->getKey() : null;

        if ($isFinancial) {
            if (DB::transactionLevel() === 0) {
                throw new \RuntimeException('SystemEventService::record() with isFinancial=true must be called within an existing DB transaction.');
            }

            $event = SystemEvent::query()->create([
                'event_type' => $eventType,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'meta' => $meta !== [] ? $meta : null,
                'severity' => $severityValue,
                'is_financial' => true,
            ]);

            DB::afterCommit(function () use ($event): void {
                event(new SystemEventCreated($event->id));
            });

            return $event;
        }

        $idempotencyKey = $this->structuredIdempotencyKey($eventType, $entityType, $entityId, $idempotencySuffix);

        PersistSystemEventJob::dispatch(
            $eventType,
            $entityType,
            $entityId,
            $actorType,
            $actorId,
            $meta,
            $severityValue,
            $idempotencyKey,
        );

        return null;
    }

    /**
     * Structured idempotency key: deterministic, no meta/hash. Use suffix for repeatable-per-entity events.
     */
    private function structuredIdempotencyKey(
        string $eventType,
        ?string $entityType,
        ?int $entityId,
        ?string $suffix,
    ): string {
        $base = sprintf('async:%s:%s:%s', $eventType, $entityType ?? 'n', $entityId ?? 0);

        return $suffix !== null && $suffix !== '' ? $base.':'.$suffix : $base;
    }
}
