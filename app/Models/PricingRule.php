<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PricingRule extends Model
{
    protected $fillable = [
        'min_price',
        'max_price',
        'wholesale_percentage',
        'retail_percentage',
        'priority',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_price' => 'decimal:2',
            'max_price' => 'decimal:2',
            'wholesale_percentage' => 'decimal:2',
            'retail_percentage' => 'decimal:2',
            'priority' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
