<?php

namespace App\Models;

use App\Services\PriceCalculator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Product extends Model
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use HasFactory;

    /**
     * Defaults for legacy NOT NULL columns; reads use accessors (derived from entry_price).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'retail_price' => 0,
        'wholesale_price' => 0,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'package_id',
        'serial',
        'name',
        'slug',
        'entry_price',
        'retail_price',
        'wholesale_price',
        'is_active',
        'order',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'package_id' => 'integer',
            'entry_price' => 'decimal:2',
            'retail_price' => 'decimal:2',
            'wholesale_price' => 'decimal:2',
            'is_active' => 'boolean',
            'order' => 'integer',
            'serial' => 'string',
        ];
    }

    /**
     * Derived from entry_price via active pricing rules (bankers rounding).
     * Fallback: stored retail_price when entry_price is null (migration phase).
     */
    public function getRetailPriceAttribute(?float $value): float
    {
        $entryPrice = $this->attributes['entry_price'] ?? null;

        if ($entryPrice !== null && $entryPrice !== '') {
            $prices = app(PriceCalculator::class)->calculate((float) $entryPrice);

            return $prices['retail_price'];
        }

        return (float) ($value ?? $this->attributes['retail_price'] ?? 0);
    }

    /**
     * Derived from entry_price via active pricing rules (bankers rounding).
     * Fallback: stored wholesale_price when entry_price is null (migration phase).
     */
    public function getWholesalePriceAttribute(?float $value): float
    {
        $entryPrice = $this->attributes['entry_price'] ?? null;

        if ($entryPrice !== null && $entryPrice !== '') {
            $prices = app(PriceCalculator::class)->calculate((float) $entryPrice);

            return $prices['wholesale_price'];
        }

        return (float) ($value ?? $this->attributes['wholesale_price'] ?? 0);
    }

    protected static function booted(): void
    {
        parent::boot();

        static::creating(function (self $product): void {
            $product->slug = $product->slug ?? Str::slug($product->name);
        });

        static::updating(function (self $product): void {
            if ($product->isDirty('name')) {
                $product->slug = Str::slug($product->name);
            }
        });
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }
}
