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
use App\Notifications\RefundRequestedNotification;
use App\Services\NotificationRecipientService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RefundOrderItem
{
    public function handle(Fulfillment $fulfillment, int $actorId, ?string $note = null): WalletTransaction
    {
        return DB::transaction(function () use ($fulfillment, $actorId, $note): WalletTransaction {
            $lockedFulfillment = Fulfillment::query()
                ->whereKey($fulfillment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedItem = OrderItem::query()
                ->whereKey($lockedFulfillment->order_item_id)
                ->lockForUpdate()
                ->firstOrFail();

            $order = Order::query()
                ->whereKey($lockedItem->order_id)
                ->lockForUpdate()
                ->firstOrFail();

            $actor = User::query()->find($actorId);

            if ($actor === null) {
                throw ValidationException::withMessages([
                    'order_item' => __('messages.refund_not_allowed'),
                ]);
            }

            if ($order->user_id !== $actorId && ! $actor->can('process_refunds')) {
                throw ValidationException::withMessages([
                    'order_item' => __('messages.refund_not_allowed'),
                ]);
            }

            if ($lockedFulfillment->status !== FulfillmentStatus::Failed) {
                throw ValidationException::withMessages([
                    'order_item' => __('messages.refund_not_allowed'),
                ]);
            }

            $existing = WalletTransaction::query()
                ->where('reference_type', Fulfillment::class)
                ->where('reference_id', $lockedFulfillment->id)
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

            if ((float) $lockedItem->unit_price <= 0) {
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
                'amount' => $lockedItem->unit_price,
                'status' => WalletTransaction::STATUS_PENDING,
                'reference_type' => Fulfillment::class,
                'reference_id' => $lockedFulfillment->id,
                'meta' => array_filter([
                    'state' => 'refund_requested',
                    'requested_at' => now()->toIso8601String(),
                    'requester_id' => $actorId,
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'order_item_id' => $lockedItem->id,
                    'fulfillment_id' => $lockedFulfillment->id,
                    'user_id' => $order->user_id,
                    'currency' => 'USD',
                    'note' => $note,
                ], fn ($value) => $value !== null && $value !== ''),
            ]);

            activity()
                ->inLog('payments')
                ->event('refund.requested')
                ->performedOn($transaction)
                ->causedBy($actor)
                ->withProperties(array_filter([
                    'transaction_id' => $transaction->id,
                    'order_id' => $order->id,
                    'order_item_id' => $lockedItem->id,
                    'fulfillment_id' => $lockedFulfillment->id,
                    'wallet_id' => $wallet->id,
                    'amount' => $transaction->amount,
                    'currency' => 'USD',
                    'note' => $note,
                ], fn ($value) => $value !== null && $value !== ''))
                ->log('Refund requested');

            $fulfillmentMeta = $lockedFulfillment->meta ?? [];
            $fulfillmentMeta['refund'] = array_filter([
                'status' => WalletTransaction::STATUS_PENDING,
                'wallet_transaction_id' => $transaction->id,
                'requested_by' => $actorId,
                'requested_at' => now()->toIso8601String(),
                'note' => $note,
            ], fn ($value) => $value !== null && $value !== '');

            $lockedFulfillment->update([
                'meta' => $fulfillmentMeta,
            ]);

            app(AppendFulfillmentLog::class)->handle(
                $lockedFulfillment,
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

            $transactionId = $transaction->id;
            DB::afterCommit(function () use ($transactionId): void {
                $tx = WalletTransaction::query()->find($transactionId);
                if ($tx === null) {
                    return;
                }
                $notification = RefundRequestedNotification::fromRefundTransaction($tx);
                app(NotificationRecipientService::class)->adminUsers()->each(fn ($admin) => $admin->notify($notification));
            });

            return $transaction;
        });
    }
}
