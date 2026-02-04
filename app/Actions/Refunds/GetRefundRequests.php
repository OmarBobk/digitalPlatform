<?php

declare(strict_types=1);

namespace App\Actions\Refunds;

use App\Enums\WalletTransactionType;
use App\Models\Fulfillment;
use App\Models\OrderItem;
use App\Models\WalletTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class GetRefundRequests
{
    public function handle(int $perPage): LengthAwarePaginator
    {
        return WalletTransaction::query()
            ->select([
                'id',
                'wallet_id',
                'type',
                'direction',
                'amount',
                'status',
                'reference_type',
                'reference_id',
                'meta',
                'created_at',
            ])
            ->where('type', WalletTransactionType::Refund->value)
            ->where('status', WalletTransaction::STATUS_PENDING)
            ->with([
                'reference' => function (MorphTo $morphTo): void {
                    $morphTo->morphWith([
                        Fulfillment::class => [
                            'orderItem.order.user:id,name,email',
                            'orderItem',
                            'order',
                        ],
                        OrderItem::class => [
                            'order.user:id,name,email',
                            'fulfillments',
                        ],
                    ]);
                },
            ])
            ->latest('created_at')
            ->paginate($perPage);
    }
}
