<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FulfillmentLogLevel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fulfillment logs are admin-only audit/debug entries.
 */
class FulfillmentLog extends Model
{
    /** @use HasFactory<\Database\Factories\FulfillmentLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'fulfillment_id',
        'level',
        'message',
        'context',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fulfillment_id' => 'integer',
            'level' => FulfillmentLogLevel::class,
            'context' => 'array',
        ];
    }

    public function fulfillment(): BelongsTo
    {
        return $this->belongsTo(Fulfillment::class);
    }
}
