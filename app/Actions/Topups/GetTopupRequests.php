<?php

declare(strict_types=1);

namespace App\Actions\Topups;

use App\Enums\TopupRequestStatus;
use App\Models\TopupRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetTopupRequests
{
    public function handle(string $statusFilter, int $perPage): LengthAwarePaginator
    {
        $query = TopupRequest::query()
            ->select([
                'id',
                'user_id',
                'wallet_id',
                'method',
                'amount',
                'currency',
                'status',
                'created_at',
                'approved_by',
                'approved_at',
            ])
            ->with('user:id,name,email')
            ->latest('created_at');

        if ($statusFilter !== 'all') {
            $status = TopupRequestStatus::tryFrom($statusFilter);

            if ($status !== null) {
                $query->where('status', $status->value);
            }
        }

        return $query->paginate($perPage);
    }
}
