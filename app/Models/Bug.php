<?php

namespace App\Models;

use App\Events\BugInboxChanged;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Bug extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_CLOSED = 'closed';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'role',
        'scenario',
        'subtype',
        'severity',
        'status',
        'trace_id',
        'current_url',
        'route_name',
        'description',
        'metadata',
        'potential_duplicate_of',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'metadata' => 'array',
            'potential_duplicate_of' => 'integer',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_OPEN,
            self::STATUS_IN_PROGRESS,
            self::STATUS_RESOLVED,
            self::STATUS_CLOSED,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(BugStep::class)->orderBy('step_order');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(BugAttachment::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(BugLink::class);
    }

    public function potentialDuplicate(): BelongsTo
    {
        return $this->belongsTo(self::class, 'potential_duplicate_of');
    }

    protected static function booted(): void
    {
        static::created(function (Bug $bug): void {
            static::broadcastInboxChangedAfterCommit($bug->id, 'created');
        });

        static::updated(function (Bug $bug): void {
            if ($bug->wasChanged('status')) {
                static::broadcastInboxChangedAfterCommit($bug->id, 'status-updated');
            }
        });

        static::deleted(function (Bug $bug): void {
            static::broadcastInboxChangedAfterCommit($bug->id, 'deleted');
        });
    }

    public function scopeOpenOrInProgress(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_OPEN, self::STATUS_IN_PROGRESS]);
    }

    protected static function broadcastInboxChangedAfterCommit(?int $bugId, string $reason): void
    {
        DB::afterCommit(static function () use ($bugId, $reason): void {
            event(new BugInboxChanged($bugId, $reason));
        });
    }
}
