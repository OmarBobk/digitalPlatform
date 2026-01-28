<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TopupMethod;
use App\Enums\TopupRequestStatus;
use App\Enums\WalletTransactionDirection;
use App\Enums\WalletTransactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class TopupRequest extends Model
{
    /** @use HasFactory<\Database\Factories\TopupRequestFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'wallet_id',
        'method',
        'amount',
        'currency',
        'status',
        'note',
        'approved_by',
        'approved_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'wallet_id' => 'integer',
            'method' => TopupMethod::class,
            'amount' => 'decimal:2',
            'currency' => 'string',
            'status' => TopupRequestStatus::class,
            'approved_by' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $topupRequest): void {
            if ($topupRequest->wallet_id !== null) {
                return;
            }

            $user = $topupRequest->user ?? User::query()->find($topupRequest->user_id);

            if ($user === null) {
                return;
            }

            $topupRequest->wallet_id = Wallet::forUser($user)->id;
        });

        static::created(function (self $topupRequest): void {
            if ($topupRequest->walletTransaction()->exists()) {
                return;
            }

            $topupRequest->walletTransaction()->create([
                'wallet_id' => $topupRequest->wallet_id,
                'type' => WalletTransactionType::Topup,
                'direction' => WalletTransactionDirection::Credit,
                'amount' => $topupRequest->amount,
                'status' => WalletTransaction::STATUS_PENDING,
                'meta' => [
                    'method' => $topupRequest->method->value,
                    'note' => $topupRequest->note,
                ],
            ]);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function proofs(): HasMany
    {
        return $this->hasMany(TopupProof::class);
    }

    public function walletTransaction(): MorphOne
    {
        return $this->morphOne(WalletTransaction::class, 'reference');
    }
}
