<?php

declare(strict_types=1);

namespace App\Actions\Topups;

use App\Enums\TopupRequestStatus;
use App\Models\TopupRequest;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

class RejectTopupRequest
{
    /**
     * Reject a pending top-up without changing wallet balance.
     */
    public function handle(TopupRequest $topupRequest): TopupRequest
    {
        return DB::transaction(function () use ($topupRequest): TopupRequest {
            $request = TopupRequest::query()
                ->whereKey($topupRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($request->status === TopupRequestStatus::Rejected) {
                return $request;
            }

            if ($request->status !== TopupRequestStatus::Pending) {
                return $request;
            }

            $transaction = WalletTransaction::query()
                ->where('reference_type', TopupRequest::class)
                ->where('reference_id', $request->id)
                ->lockForUpdate()
                ->first();

            if ($transaction !== null && $transaction->status === WalletTransaction::STATUS_PENDING) {
                $transaction->status = WalletTransaction::STATUS_REJECTED;
                $transaction->save();
            }

            $request->fill([
                'status' => TopupRequestStatus::Rejected,
                'approved_by' => null,
                'approved_at' => null,
            ])->save();

            return $request;
        });
    }
}
