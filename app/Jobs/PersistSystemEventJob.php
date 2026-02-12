<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\SystemEventCreated;
use App\Models\SystemEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PersistSystemEventJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly string $eventType,
        public readonly ?string $entityType,
        public readonly ?int $entityId,
        public readonly ?string $actorType,
        public readonly ?int $actorId,
        public readonly array $meta,
        public readonly string $severity,
        public readonly string $idempotencyKey,
    ) {}

    public function handle(): void
    {
        $existing = SystemEvent::query()
            ->where('idempotency_key', $this->idempotencyKey)
            ->first();

        if ($existing !== null) {
            return;
        }

        try {
            $event = SystemEvent::query()->create([
                'event_type' => $this->eventType,
                'entity_type' => $this->entityType,
                'entity_id' => $this->entityId,
                'actor_type' => $this->actorType,
                'actor_id' => $this->actorId,
                'meta' => $this->meta !== [] ? $this->meta : null,
                'severity' => $this->severity,
                'is_financial' => false,
                'idempotency_key' => $this->idempotencyKey,
            ]);

            event(new SystemEventCreated($event->id));
        } catch (\Throwable $e) {
            if ($this->isDuplicateKeyException($e)) {
                return;
            }
            throw $e;
        }
    }

    private function isDuplicateKeyException(\Throwable $e): bool
    {
        if ($e instanceof \Illuminate\Database\QueryException) {
            return (int) $e->getCode() === 23000
                && str_contains($e->getMessage(), 'idempotency_key');
        }

        return false;
    }
}
