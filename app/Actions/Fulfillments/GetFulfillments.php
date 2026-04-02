<?php

declare(strict_types=1);

namespace App\Actions\Fulfillments;

use App\Enums\FulfillmentStatus;
use App\Models\Fulfillment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class GetFulfillments
{
    public function handle(
        string $search,
        string $statusFilter,
        int $perPage,
        string $scope = 'all',
        ?int $actorId = null,
        bool $isAdmin = false,
        ?int $claimedBy = null,
        ?int $handledByActorId = null,
        ?string $customerSearch = null,
        ?string $orderSearch = null
    ): LengthAwarePaginator {
        $search = trim($search);
        $customerSearch = trim((string) $customerSearch);
        $orderSearch = trim((string) $orderSearch);

        $isUnclaimedScope = $scope === 'unclaimed' || (! $isAdmin && $actorId !== null && $scope !== 'mine');

        $query = Fulfillment::query()
            ->select(
                $isUnclaimedScope
                    ? [
                        'id',
                        'order_id',
                        'order_item_id',
                        'provider',
                        'status',
                        'created_at',
                        'updated_at',
                    ]
                    : [
                        'id',
                        'order_id',
                        'order_item_id',
                        'claimed_by',
                        'provider',
                        'status',
                        'attempts',
                        'last_error',
                        'processed_at',
                        'completed_at',
                        'claimed_at',
                        'meta',
                        'created_at',
                        'updated_at',
                    ]
            )
            ->with(
                $isUnclaimedScope
                    ? [
                        'order:id,user_id,order_number',
                        'order.user:id,name,email,username',
                        'orderItem:id,order_id,product_id,name,unit_price,amount_mode,requested_amount,amount_unit_label,line_total,requirements_payload',
                        'orderItem.product:id,slug',
                    ]
                    : [
                        'order:id,user_id,order_number,total,currency,created_at',
                        'order.user:id,name,email,username',
                        'orderItem:id,order_id,product_id,package_id,name,unit_price,quantity,amount_mode,requested_amount,amount_unit_label,line_total,requirements_payload',
                        'orderItem.product:id,name,slug',
                        'claimer:id,username,name',
                    ]
            )
            ->latest('created_at');

        if (! $isAdmin && $actorId !== null) {
            if ($scope === 'mine') {
                $query->where('claimed_by', $actorId);
            } else {
                $query
                    ->where('status', FulfillmentStatus::Queued)
                    ->whereNull('claimed_by');
            }
        } elseif ($scope === 'unclaimed') {
            $query
                ->where('status', FulfillmentStatus::Queued)
                ->whereNull('claimed_by');
        } elseif ($scope === 'mine' && $actorId !== null) {
            $query->where('claimed_by', $actorId);
        }

        if ($claimedBy !== null) {
            $query->where('claimed_by', $claimedBy);
        }

        if ($handledByActorId !== null) {
            $query->where(function (Builder $query) use ($handledByActorId): void {
                $query
                    ->where('claimed_by', $handledByActorId)
                    ->orWhere(function (Builder $query) use ($handledByActorId): void {
                        $query
                            ->whereNull('claimed_by')
                            ->whereHas('logs', function (Builder $query) use ($handledByActorId): void {
                                $query
                                    ->whereIn('message', ['Fulfillment completed', 'Fulfillment failed'])
                                    ->where('context->actor_id', $handledByActorId);
                            });
                    });
            });
        }

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

        if ($customerSearch !== '') {
            $query->whereHas('order.user', function (Builder $query) use ($customerSearch): void {
                $like = '%'.$customerSearch.'%';

                $query
                    ->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('username', 'like', $like);

                if (ctype_digit($customerSearch)) {
                    $query->orWhere('id', (int) $customerSearch);
                }
            });
        }

        if ($orderSearch !== '') {
            $query->where(function (Builder $query) use ($orderSearch): void {
                $like = '%'.$orderSearch.'%';

                $query->whereHas('order', function (Builder $orderQuery) use ($like, $orderSearch): void {
                    $orderQuery->where('order_number', 'like', $like);

                    if (ctype_digit($orderSearch)) {
                        $orderQuery->orWhere('id', (int) $orderSearch);
                    }
                });

                if (ctype_digit($orderSearch)) {
                    $query->orWhere('order_id', (int) $orderSearch);
                }
            });
        }

        return $query->paginate($perPage);
    }
}
