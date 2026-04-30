<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommissionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Commission extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'fulfillment_id',
        'salesperson_id',
        'customer_id',
        'referral_code',
        'order_total',
        'commission_amount',
        'status',
        'paid_at',
        'paid_method',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'fulfillment_id' => 'integer',
            'salesperson_id' => 'integer',
            'customer_id' => 'integer',
            'referral_code' => 'string',
            'order_total' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'status' => CommissionStatus::class,
            'paid_at' => 'datetime',
            'paid_method' => 'string',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function fulfillment(): BelongsTo
    {
        return $this->belongsTo(Fulfillment::class);
    }

    public function salesperson(): BelongsTo
    {
        return $this->belongsTo(User::class, 'salesperson_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }
}
