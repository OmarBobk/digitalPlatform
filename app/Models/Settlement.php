<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Settlement batches completed fulfillments for profit credit to platform wallet.
 * total_amount = sum of per-fulfillment profit from {@see \App\Services\SettlementProfitCalculator}.
 */
class Settlement extends Model
{
    protected $fillable = ['total_amount'];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
        ];
    }

    public function fulfillments(): BelongsToMany
    {
        return $this->belongsToMany(Fulfillment::class, 'settlement_fulfillments')
            ->withTimestamps();
    }
}
