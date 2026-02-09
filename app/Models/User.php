<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\LoyaltyTier;
use App\Enums\Timezone;
use App\Enums\WalletType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'is_active',
        'phone',
        'country_code',
        'timezone',
        'profile_photo',
        'blocked_at',
        'last_login_at',
        'loyalty_tier',
        'loyalty_evaluated_at',
        'loyalty_locked_until',
        'loyalty_override_by',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'timezone' => Timezone::class,
            'blocked_at' => 'datetime',
            'last_login_at' => 'datetime',
            'loyalty_tier' => LoyaltyTier::class,
            'loyalty_evaluated_at' => 'datetime',
            'loyalty_locked_until' => 'datetime',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    /**
     * Check if the user is active.
     */
    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    /**
     * Check if the user is blocked.
     */
    public function isBlocked(): bool
    {
        return $this->blocked_at !== null;
    }

    /**
     * Check if the user can login.
     */
    public function canLogin(): bool
    {
        return $this->isActive() && ! $this->isBlocked();
    }

    /**
     * Customer wallet for this user (for balance display, topups, checkout).
     * Scoped to Customer type so platform wallet is never returned.
     */
    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class)->where('type', WalletType::Customer);
    }

    /**
     * Admin who set a loyalty override (lock) on this user.
     */
    public function loyaltyOverrideBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'loyalty_override_by');
    }

    /**
     * Whether the user's loyalty tier is currently locked (override active).
     */
    public function isLoyaltyLocked(): bool
    {
        return $this->loyalty_locked_until !== null
            && $this->loyalty_locked_until->isFuture();
    }

    /**
     * Role used for loyalty tier ladder and evaluation. Customer vs salesperson get different thresholds.
     */
    public function loyaltyRole(): ?string
    {
        if ($this->hasRole('customer')) {
            return 'customer';
        }
        if ($this->hasRole('salesperson') || $this->hasRole('supervisor')) {
            return 'salesperson';
        }

        return null;
    }
}
