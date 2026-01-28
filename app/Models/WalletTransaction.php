<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WalletTransactionDirection;
use App\Enums\WalletTransactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WalletTransaction extends Model
{
    /** @use HasFactory<\Database\Factories\WalletTransactionFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_POSTED = 'posted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_POSTED,
        self::STATUS_REJECTED,
    ];

    public const UPDATED_AT = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'wallet_id',
        'type',
        'direction',
        'amount',
        'status',
        'reference_type',
        'reference_id',
        'idempotency_key',
        'meta',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'wallet_id' => 'integer',
            'type' => WalletTransactionType::class,
            'direction' => WalletTransactionDirection::class,
            'amount' => 'decimal:2',
            'reference_id' => 'integer',
            'meta' => 'array',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
