<?php

declare(strict_types=1);

namespace App\Actions\Topups;

use App\Enums\TopupRequestStatus;
use App\Enums\WalletTransactionDirection;
use App\Models\TopupRequest;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ApproveTopupRequest
{
    /**
     * Post the ledger entry and update balance only once.
     */
    public function handle(TopupRequest $topupRequest, int $approvedById): TopupRequest
    {
        return DB::transaction(function () use ($topupRequest, $approvedById): TopupRequest {
            $request = TopupRequest::query()
                ->whereKey($topupRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($request->status === TopupRequestStatus::Approved) {
                return $request;
            }

            $transaction = WalletTransaction::query()
                ->where('reference_type', TopupRequest::class)
                ->where('reference_id', $request->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($transaction->status === WalletTransaction::STATUS_POSTED) {
                return $request;
            }

            if ($request->status !== TopupRequestStatus::Pending) {
                return $request;
            }

            $wallet = Wallet::query()
                ->whereKey($request->wallet_id)
                ->lockForUpdate()
                ->first();

            if ($wallet === null) {
                $user = User::query()->find($request->user_id);

                if ($user === null) {
                    throw new \RuntimeException('Wallet user not found.');
                }

                $wallet = Wallet::forUser($user);
                $request->wallet_id = $wallet->id;
                $request->save();
            }

            if ($transaction->direction !== WalletTransactionDirection::Credit) {
                throw new \RuntimeException('Top-up transaction must be credit.');
            }

            if ($transaction->status === WalletTransaction::STATUS_PENDING) {
                if ((float) $transaction->amount <= 0) {
                    throw new \RuntimeException('Top-up amount must be greater than zero.');
                }

                if ($wallet->currency !== $request->currency) {
                    throw new \RuntimeException('Wallet currency does not match top-up request.');
                }

                $transaction->status = WalletTransaction::STATUS_POSTED;
                $transaction->meta = array_merge($transaction->meta ?? [], [
                    'approved_by' => $approvedById,
                    'approved_at' => now()->toIso8601String(),
                ]);
                $transaction->save();

                $wallet->increment('balance', $transaction->amount);
            }

            $request->fill([
                'status' => TopupRequestStatus::Approved,
                'approved_by' => $approvedById,
                'approved_at' => now(),
            ])->save();

            if (Schema::hasTable('activity_log')) {
                $admin = User::query()->find($approvedById);

                activity()
                    ->inLog('payments')
                    ->event('topup.approved')
                    ->performedOn($request)
                    ->causedBy($admin)
                    ->withProperties([
                        'topup_request_id' => $request->id,
                        'wallet_id' => $request->wallet_id,
                        'user_id' => $request->user_id,
                        'amount' => $request->amount,
                        'currency' => $request->currency,
                        'transaction_id' => $transaction->id,
                    ])
                    ->log('Topup approved');

                activity()
                    ->inLog('payments')
                    ->event('wallet.credited')
                    ->performedOn($wallet)
                    ->causedBy($admin)
                    ->withProperties([
                        'wallet_id' => $wallet->id,
                        'user_id' => $wallet->user_id,
                        'amount' => $transaction->amount,
                        'currency' => $wallet->currency,
                        'transaction_id' => $transaction->id,
                        'source' => 'topup',
                    ])
                    ->log('Wallet credited');
            }

            return $request;
        });
    }
}
