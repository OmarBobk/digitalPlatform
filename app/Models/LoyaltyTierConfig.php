<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class LoyaltyTierConfig extends Model
{
    protected $table = 'loyalty_tiers';

    protected $fillable = [
        'role',
        'name',
        'min_spend',
        'discount_percentage',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_spend' => 'decimal:2',
            'discount_percentage' => 'decimal:2',
        ];
    }

    public function scopeForRole(Builder $query, ?string $role): Builder
    {
        if ($role === null || $role === '') {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('role', $role);
    }
}
