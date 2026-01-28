<?php

declare(strict_types=1);

namespace App\Actions\Fulfillments;

use App\Enums\FulfillmentStatus;
use App\Models\Fulfillment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class GetFulfillments
{
    public function handle(string $search, string $statusFilter, int $perPage): LengthAwarePaginator
    {
        $search = trim($search);

        $query = Fulfillment::query()
            ->select([
                'id',
                'order_id',
                'order_item_id',
                'provider',
                'status',
                'attempts',
                'last_error',
                'processed_at',
                'completed_at',
                'meta',
                'created_at',
                'updated_at',
            ])
            ->with([
                'order:id,user_id,order_number,total,currency,created_at',
                'order.user:id,name,email',
                'orderItem:id,order_id,product_id,package_id,name,quantity',
                'orderItem.product:id,name,slug',
            ])
            ->latest('created_at');

        if ($statusFilter !== 'all') {
            $status = FulfillmentStatus::tryFrom($statusFilter);

            if ($status !== null) {
                $query->where('status', $status->value);
            }
        }

        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $like = '%'.$search.'%';

                if (ctype_digit($search)) {
                    $query->where('order_id', (int) $search)
                        ->orWhere('order_item_id', (int) $search)
                        ->orWhereHas('order', fn (Builder $query) => $query->where('user_id', (int) $search));
                }

                $query->orWhereHas('order', function (Builder $query) use ($like): void {
                    $query->where('order_number', 'like', $like)
                        ->orWhere('currency', 'like', $like);
                });

                $query->orWhereHas('order.user', function (Builder $query) use ($like): void {
                    $query->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like);
                });
            });
        }

        return $query->paginate($perPage);
    }
}
