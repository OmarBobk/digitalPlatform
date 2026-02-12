<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\SystemEventSeverity;
use Carbon\CarbonInterface;

/**
 * Single entry for the unified user audit timeline.
 * Used for merging system_events, wallet_transactions, orders, fulfillments, etc.
 *
 * eventType: domain event_type from system_events (e.g. refund.requested); null for non-system sources.
 * Use eventType for domain-safe filtering; use title for display only.
 */
final class TimelineEntryDTO
{
    public function __construct(
        public string $type,
        public string $title,
        public string $description,
        public CarbonInterface $occurredAt,
        public SystemEventSeverity $severity,
        public bool $isFinancial,
        /** @var array<string, mixed> */
        public array $meta,
        /** Unique key for deduplication (e.g. "system_event:123", "order:456") */
        public string $sourceKey,
        /** Domain event_type from system_events; null for wallet_transaction, order, fulfillment. */
        public ?string $eventType = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'title' => $this->title,
            'description' => $this->description,
            'occurredAt' => $this->occurredAt->toIso8601String(),
            'severity' => $this->severity->value,
            'isFinancial' => $this->isFinancial,
            'meta' => $this->meta,
            'sourceKey' => $this->sourceKey,
            'eventType' => $this->eventType,
        ];
    }
}
