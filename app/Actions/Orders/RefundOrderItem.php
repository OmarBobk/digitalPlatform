<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Actions\Fulfillments\AppendFulfillmentLog;
use App\Enums\FulfillmentLogLevel;
use App\Enums\FulfillmentStatus;
use App\Enums\WalletTransactionDirection;
use App\Enums\WalletTransactionType;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RefundOrderItem
{
    public function handle(OrderItem $orderItem, int $actorId, ?string $note = null): WalletTransaction
    {
        return DB::transaction(function () use ($orderItem, $actorId, $note): WalletTransaction {
            $lockedItem = OrderItem::query()
                ->whereKey($orderItem->id)
                ->lockForUpdate()
                ->firstOrFail();

            $order = Order::query()
                ->whereKey($lockedItem->order_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->user_id !== $actorId) {
                throw ValidationException::withMessages([
                    'order_item' => __('messages.refund_not_allowed'),
                ]);
            }

            $fulfillment = Fulfillment::query()
                ->where('order_item_id', $lockedItem->id)
                ->lockForUpdate()
                ->first();

            if ($fulfillment === null || $fulfillment->status !== FulfillmentStatus::Failed) {
                throw ValidationException::withMessages([
                    'order_item' => __('messages.refund_not_allowed'),
                ]);
            }

            $existing = WalletTransaction::query()
                ->where('reference_type', OrderItem::class)
                ->where('reference_id', $lockedItem->id)
                ->where('type', WalletTransactionType::Refund->value)
                ->whereIn('status', [WalletTransaction::STATUS_PENDING, WalletTransaction::STATUS_POSTED])
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $wallet = Wallet::query()
                ->where('user_id', $order->user_id)
                ->lockForUpdate()
                ->first();

            if ($wallet === null) {
                $user = User::query()->find($order->user_id);

                if ($user === null) {
                    throw ValidationException::withMessages([
                        'order_item' => __('messages.refund_not_allowed'),
                    ]);
                }

                $wallet = Wallet::forUser($user);
                $wallet = Wallet::query()
                    ->whereKey($wallet->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            if ((float) $lockedItem->line_total <= 0) {
                throw ValidationException::withMessages([
                    'order_item' => __('messages.refund_not_allowed'),
                ]);
            }

            if ($wallet->currency !== 'USD' || $order->currency !== 'USD') {
                throw ValidationException::withMessages([
                    'order_item' => __('messages.refund_not_allowed'),
                ]);
            }

            $transaction = WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'type' => WalletTransactionType::Refund,
                'direction' => WalletTransactionDirection::Credit,
                'amount' => $lockedItem->line_total,
                'status' => WalletTransaction::STATUS_PENDING,
                'reference_type' => OrderItem::class,
                'reference_id' => $lockedItem->id,
                'meta' => array_filter([
                    'state' => 'refund_requested',
                    'requested_at' => now()->toIso8601String(),
                    'requester_id' => $actorId,
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'order_item_id' => $lockedItem->id,
                    'fulfillment_id' => $fulfillment->id,
                    'user_id' => $order->user_id,
                    'currency' => 'USD',
                    'note' => $note,
                ], fn ($value) => $value !== null && $value !== ''),
            ]);

            activity()
                ->inLog('payments')
                ->event('refund.requested')
                ->performedOn($transaction)
                ->causedBy(User::query()->find($actorId))
                ->withProperties(array_filter([
                    'transaction_id' => $transaction->id,
                    'order_id' => $order->id,
                    'order_item_id' => $lockedItem->id,
                    'fulfillment_id' => $fulfillment->id,
                    'wallet_id' => $wallet->id,
                    'amount' => $transaction->amount,
                    'currency' => 'USD',
                    'note' => $note,
                ], fn ($value) => $value !== null && $value !== ''))
                ->log('Refund requested');

            $fulfillmentMeta = $fulfillment->meta ?? [];
            $fulfillmentMeta['refund'] = array_filter([
                'status' => WalletTransaction::STATUS_PENDING,
                'wallet_transaction_id' => $transaction->id,
                'requested_by' => $actorId,
                'requested_at' => now()->toIso8601String(),
                'note' => $note,
            ], fn ($value) => $value !== null && $value !== '');

            $fulfillment->update([
                'meta' => $fulfillmentMeta,
            ]);

            app(AppendFulfillmentLog::class)->handle(
                $fulfillment,
                FulfillmentLogLevel::Info,
                'Refund requested',
                array_filter([
                    'action' => 'refund_requested',
                    'actor_type' => 'user',
                    'actor_id' => $actorId,
                    'order_item_id' => $lockedItem->id,
                    'transaction_id' => $transaction->id,
                    'note' => $note,
                ], fn ($value) => $value !== null && $value !== '')
            );

            return $transaction;
        });
    }
}
