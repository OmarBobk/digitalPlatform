<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderItemStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Order items snapshot name/unit_price to preserve historical pricing.
 */
class OrderItem extends Model
{
    /** @use HasFactory<\Database\Factories\OrderItemFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'product_id',
        'package_id',
        'name',
        'unit_price',
        'entry_price',
        'quantity',
        'line_total',
        'requirements_payload',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'product_id' => 'integer',
            'package_id' => 'integer',
            'unit_price' => 'decimal:2',
            'entry_price' => 'decimal:2',
            'quantity' => 'integer',
            'line_total' => 'decimal:2',
            'requirements_payload' => 'array',
            'status' => OrderItemStatus::class,
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function fulfillments(): HasMany
    {
        return $this->hasMany(Fulfillment::class);
    }

    public function aggregateFulfillmentStatus(?Collection $fulfillments = null): ?FulfillmentStatus
    {
        $fulfillments = $fulfillments
            ?? ($this->relationLoaded('fulfillments') ? $this->fulfillments : $this->fulfillments()->get());

        if ($fulfillments->isEmpty()) {
            return null;
        }

        if ($fulfillments->contains(fn (Fulfillment $fulfillment) => $fulfillment->status === FulfillmentStatus::Failed)) {
            return FulfillmentStatus::Failed;
        }

        if ($fulfillments->contains(fn (Fulfillment $fulfillment) => $fulfillment->status === FulfillmentStatus::Processing)) {
            return FulfillmentStatus::Processing;
        }

        if ($fulfillments->contains(fn (Fulfillment $fulfillment) => $fulfillment->status === FulfillmentStatus::Queued)) {
            return FulfillmentStatus::Queued;
        }

        if ($fulfillments->every(fn (Fulfillment $fulfillment) => $fulfillment->status === FulfillmentStatus::Completed)) {
            return FulfillmentStatus::Completed;
        }

        return FulfillmentStatus::Queued;
    }

    public function syncStatusFromFulfillments(?Collection $fulfillments = null): OrderItemStatus
    {
        $summary = $this->aggregateFulfillmentStatus($fulfillments);

        $status = match ($summary) {
            FulfillmentStatus::Completed => OrderItemStatus::Fulfilled,
            FulfillmentStatus::Processing => OrderItemStatus::Processing,
            FulfillmentStatus::Failed => OrderItemStatus::Failed,
            default => OrderItemStatus::Pending,
        };

        if ($this->status !== $status) {
            $this->update(['status' => $status]);
        }

        return $status;
    }
}
