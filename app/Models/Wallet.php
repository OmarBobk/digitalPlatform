<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WalletType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    /** @use HasFactory<\Database\Factories\WalletFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'type',
        'balance',
        'currency',
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
            'type' => WalletType::class,
            'balance' => 'decimal:2',
            'currency' => 'string',
        ];
    }

    public static function forUser(User $user): self
    {
        return self::query()->firstOrCreate(
            ['user_id' => $user->id, 'type' => WalletType::Customer],
            [
                'type' => WalletType::Customer,
                'balance' => 0,
                'currency' => config('billing.currency', 'USD'),
            ]
        );
    }

    public static function forPlatform(): self
    {
        $wallet = self::query()->where('type', WalletType::Platform->value)->first();

        if ($wallet === null) {
            throw new \RuntimeException('Platform wallet does not exist. Run migrations.');
        }

        return $wallet;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }
}
