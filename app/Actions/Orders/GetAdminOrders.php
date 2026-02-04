<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Enums\FulfillmentStatus;
use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class GetAdminOrders
{
    public function handle(
        string $search,
        string $statusFilter,
        string $fulfillmentFilter,
        ?string $dateFrom,
        ?string $dateTo,
        int $perPage
    ): LengthAwarePaginator {
        $query = Order::query()
            ->with([
                'user:id,name,email',
                'items.fulfillments',
            ])
            ->latest('created_at');

        if ($statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('order_number', 'like', '%'.$search.'%')
                    ->orWhere('id', $search)
                    ->orWhereHas('user', function (Builder $userQuery) use ($search): void {
                        $userQuery->where('email', 'like', '%'.$search.'%')
                            ->orWhere('name', 'like', '%'.$search.'%');
                    });
            });
        }

        if ($dateFrom !== null) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo !== null) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        if ($fulfillmentFilter !== 'all') {
            $this->applyFulfillmentFilter($query, $fulfillmentFilter);
        }

        return $query->paginate($perPage);
    }

    private function applyFulfillmentFilter(Builder $query, string $filter): void
    {
        if ($filter === FulfillmentStatus::Failed->value) {
            $query->whereHas('fulfillments', fn (Builder $builder) => $builder->where('status', FulfillmentStatus::Failed));

            return;
        }

        if ($filter === FulfillmentStatus::Processing->value) {
            $query->whereHas('fulfillments', fn (Builder $builder) => $builder->where('status', FulfillmentStatus::Processing))
                ->whereDoesntHave('fulfillments', fn (Builder $builder) => $builder->where('status', FulfillmentStatus::Failed));

            return;
        }

        if ($filter === FulfillmentStatus::Queued->value) {
            $query->whereDoesntHave('fulfillments', fn (Builder $builder) => $builder->whereIn('status', [
                FulfillmentStatus::Failed,
                FulfillmentStatus::Processing,
            ]))
                ->where(function (Builder $builder): void {
                    $builder->whereHas('fulfillments', fn (Builder $subQuery) => $subQuery->where('status', FulfillmentStatus::Queued))
                        ->orWhereDoesntHave('fulfillments');
                });

            return;
        }

        if ($filter === FulfillmentStatus::Completed->value) {
            $query->whereHas('fulfillments')
                ->whereDoesntHave('fulfillments', fn (Builder $builder) => $builder->where('status', '!=', FulfillmentStatus::Completed));
        }
    }
}
