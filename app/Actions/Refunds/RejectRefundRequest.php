<?php

declare(strict_types=1);

namespace App\Actions\Refunds;

use App\Actions\Fulfillments\AppendFulfillmentLog;
use App\Enums\FulfillmentLogLevel;
use App\Enums\FulfillmentStatus;
use App\Enums\WalletTransactionType;
use App\Models\Fulfillment;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RejectRefundRequest
{
    public function handle(int $transactionId, int $adminId): WalletTransaction
    {
        return DB::transaction(function () use ($transactionId, $adminId): WalletTransaction {
            $transaction = WalletTransaction::query()
                ->whereKey($transactionId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($transaction->status === WalletTransaction::STATUS_REJECTED) {
                return $transaction;
            }

            if ($transaction->status !== WalletTransaction::STATUS_PENDING) {
                return $transaction;
            }

            if ($transaction->type !== WalletTransactionType::Refund) {
                throw ValidationException::withMessages([
                    'refund' => __('messages.refund_not_allowed'),
                ]);
            }

            if ($transaction->reference_type !== OrderItem::class) {
                throw ValidationException::withMessages([
                    'refund' => __('messages.refund_not_allowed'),
                ]);
            }

            $orderItem = OrderItem::query()
                ->whereKey($transaction->reference_id)
                ->lockForUpdate()
                ->firstOrFail();

            $fulfillment = Fulfillment::query()
                ->where('order_item_id', $orderItem->id)
                ->lockForUpdate()
                ->first();

            if ($fulfillment === null || $fulfillment->status !== FulfillmentStatus::Failed) {
                throw ValidationException::withMessages([
                    'refund' => __('messages.refund_not_allowed'),
                ]);
            }

            $transaction->status = WalletTransaction::STATUS_REJECTED;
            $transaction->meta = array_merge($transaction->meta ?? [], [
                'state' => 'refund_rejected',
                'rejected_by' => $adminId,
                'rejected_at' => now()->toIso8601String(),
            ]);
            $transaction->save();

            $fulfillmentMeta = $fulfillment->meta ?? [];
            $fulfillmentMeta['refund'] = array_merge($fulfillmentMeta['refund'] ?? [], [
                'status' => WalletTransaction::STATUS_REJECTED,
                'rejected_by' => $adminId,
                'rejected_at' => now()->toIso8601String(),
            ]);
            $fulfillment->update(['meta' => $fulfillmentMeta]);

            app(AppendFulfillmentLog::class)->handle(
                $fulfillment,
                FulfillmentLogLevel::Info,
                'Refund rejected',
                [
                    'action' => 'refund_rejected',
                    'actor_type' => 'admin',
                    'actor_id' => $adminId,
                    'transaction_id' => $transaction->id,
                ]
            );

            activity()
                ->inLog('payments')
                ->event('refund.rejected')
                ->performedOn($transaction)
                ->causedBy(User::query()->find($adminId))
                ->withProperties(array_filter([
                    'transaction_id' => $transaction->id,
                    'order_id' => data_get($transaction->meta, 'order_id'),
                    'order_item_id' => $orderItem->id,
                    'fulfillment_id' => $fulfillment->id,
                    'amount' => $transaction->amount,
                    'currency' => data_get($transaction->meta, 'currency', 'USD'),
                ], fn ($value) => $value !== null && $value !== ''))
                ->log('Refund rejected');

            return $transaction;
        });
    }
}
