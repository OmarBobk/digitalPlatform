<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SystemEventSeverity;
use Illuminate\Database\Eloquent\Model;

/**
 * Insert-only event timeline for observability. No updates or deletes in business logic.
 * Enforced at model level: update() and delete() throw.
 *
 * Source of truth: wallet_transactions (and wallet balance) are the ledger. SystemEvent
 * is a mirror for observability only. No business logic must read balance or financial
 * state from system_events; always use the ledger.
 */
class SystemEvent extends Model
{
    public const UPDATED_AT = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'event_type',
        'entity_type',
        'entity_id',
        'actor_type',
        'actor_id',
        'meta',
        'severity',
        'is_financial',
        'idempotency_key',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entity_id' => 'integer',
            'actor_id' => 'integer',
            'meta' => 'array',
            'severity' => SystemEventSeverity::class,
            'is_financial' => 'boolean',
        ];
    }

    /**
     * System events are insert-only. Updates are not allowed.
     */
    public function update(array $attributes = [], array $options = []): bool
    {
        throw new \BadMethodCallException('SystemEvent is insert-only. Updates are not allowed.');
    }

    /**
     * System events are insert-only. Deletes are not allowed.
     */
    public function delete(): ?bool
    {
        throw new \BadMethodCallException('SystemEvent is insert-only. Deletes are not allowed.');
    }
}
