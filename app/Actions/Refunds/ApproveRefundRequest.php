<?php

declare(strict_types=1);

namespace App\Actions\Refunds;

use App\Actions\Fulfillments\AppendFulfillmentLog;
use App\Enums\FulfillmentLogLevel;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\WalletTransactionDirection;
use App\Enums\WalletTransactionType;
use App\Jobs\EvaluateLoyaltyForUser;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Notifications\RefundApprovedNotification;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApproveRefundRequest
{
    public function handle(int $transactionId, int $adminId): WalletTransaction
    {
        $idempotencyKey = null;

        try {
            return DB::transaction(function () use ($transactionId, $adminId, &$idempotencyKey): WalletTransaction {
                $transaction = WalletTransaction::query()
                    ->whereKey($transactionId)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($transaction->status === WalletTransaction::STATUS_POSTED) {
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

                if ($transaction->direction !== WalletTransactionDirection::Credit) {
                    throw ValidationException::withMessages([
                        'refund' => __('messages.refund_not_allowed'),
                    ]);
                }

                if ((float) $transaction->amount <= 0) {
                    throw ValidationException::withMessages([
                        'refund' => __('messages.refund_not_allowed'),
                    ]);
                }

                if (! in_array($transaction->reference_type, [Fulfillment::class, OrderItem::class, Order::class], true)) {
                    throw ValidationException::withMessages([
                        'refund' => __('messages.refund_not_allowed'),
                    ]);
                }

                $orderId = (int) data_get($transaction->meta, 'order_id', 0);
                $orderItem = null;
                $fulfillment = null;

                if ($transaction->reference_type === Fulfillment::class) {
                    $fulfillment = Fulfillment::query()
                        ->whereKey($transaction->reference_id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $orderItem = OrderItem::query()
                        ->whereKey($fulfillment->order_item_id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $orderId = $orderItem->order_id;
                }

                if ($orderId === 0 && $transaction->reference_type === OrderItem::class) {
                    $orderItem = OrderItem::query()
                        ->whereKey($transaction->reference_id)
                        ->lockForUpdate()
                        ->firstOrFail();
                    $orderId = $orderItem->order_id;

                    $fulfillmentId = (int) data_get($transaction->meta, 'fulfillment_id', 0);
                    if ($fulfillmentId > 0) {
                        $fulfillment = Fulfillment::query()
                            ->whereKey($fulfillmentId)
                            ->lockForUpdate()
                            ->first();
                    }

                    if ($fulfillment === null) {
                        $fulfillment = Fulfillment::query()
                            ->where('order_item_id', $orderItem->id)
                            ->lockForUpdate()
                            ->oldest('id')
                            ->first();
                    }
                }

                if ($orderId === 0 && $transaction->reference_type === Order::class) {
                    $orderId = (int) $transaction->reference_id;
                }

                if ($orderId === 0) {
                    throw ValidationException::withMessages([
                        'refund' => __('messages.refund_not_allowed'),
                    ]);
                }

                $order = Order::query()
                    ->whereKey($orderId)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($order->status === OrderStatus::Refunded) {
                    return $transaction;
                }

                if (in_array($order->status, [OrderStatus::PendingPayment, OrderStatus::Cancelled], true)) {
                    throw ValidationException::withMessages([
                        'refund' => __('messages.refund_not_allowed'),
                    ]);
                }

                $orderStatusFrom = $order->status;

                if ($order->currency !== 'USD') {
                    throw ValidationException::withMessages([
                        'refund' => __('messages.refund_not_allowed'),
                    ]);
                }

                $wallet = Wallet::query()
                    ->where('user_id', $order->user_id)
                    ->lockForUpdate()
                    ->first();

                if ($wallet === null) {
                    $user = User::query()->find($order->user_id);

                    if ($user === null) {
                        throw ValidationException::withMessages([
                            'refund' => __('messages.refund_not_allowed'),
                        ]);
                    }

                    $wallet = Wallet::forUser($user);
                    $wallet = Wallet::query()
                        ->whereKey($wallet->id)
                        ->lockForUpdate()
                        ->firstOrFail();
                }

                if ($wallet->currency !== 'USD') {
                    throw ValidationException::withMessages([
                        'refund' => __('messages.refund_not_allowed'),
                    ]);
                }

                if ($fulfillment !== null && $fulfillment->status !== FulfillmentStatus::Failed) {
                    throw ValidationException::withMessages([
                        'refund' => __('messages.refund_not_allowed'),
                    ]);
                }

                $idempotencyKey = $fulfillment !== null
                    ? 'refund:fulfillment:'.$fulfillment->id
                    : ($orderItem !== null ? 'refund:order_item:'.$orderItem->id : 'refund:order:'.$order->id);
                $approvedReason = data_get($transaction->meta, 'reason') ?? data_get($transaction->meta, 'note');

                $transaction->status = WalletTransaction::STATUS_POSTED;
                $transaction->idempotency_key = $idempotencyKey;
                $transaction->meta = array_merge($transaction->meta ?? [], array_filter([
                    'state' => 'refund_posted',
                    'admin_id' => $adminId,
                    'approved_by' => $adminId,
                    'approved_at' => now()->toIso8601String(),
                    'reason' => $approvedReason,
                    'order_id' => $order->id,
                ], fn ($value) => $value !== null && $value !== ''));
                $transaction->save();

                $wallet->increment('balance', $transaction->amount);

                $orderRefunded = false;
                if ($transaction->reference_type === Order::class && $order->status !== OrderStatus::Refunded) {
                    $order->update(['status' => OrderStatus::Refunded]);
                    $orderRefunded = true;
                }

                if ($fulfillment !== null) {
                    $fulfillmentMeta = $fulfillment->meta ?? [];
                    $fulfillmentMeta['refund'] = array_merge($fulfillmentMeta['refund'] ?? [], [
                        'status' => WalletTransaction::STATUS_POSTED,
                        'approved_by' => $adminId,
                        'approved_at' => now()->toIso8601String(),
                    ]);
                    $fulfillment->update(['meta' => $fulfillmentMeta]);

                    app(AppendFulfillmentLog::class)->handle(
                        $fulfillment,
                        FulfillmentLogLevel::Info,
                        'Refund approved',
                        [
                            'action' => 'refunded',
                            'actor_type' => 'admin',
                            'actor_id' => $adminId,
                            'transaction_id' => $transaction->id,
                        ]
                    );
                }

                if ($fulfillment !== null && ! $orderRefunded) {
                    $orderFulfillments = Fulfillment::query()
                        ->where('order_id', $order->id)
                        ->lockForUpdate()
                        ->get();

                    $allRefunded = $orderFulfillments->isNotEmpty()
                        && $orderFulfillments->every(
                            fn (Fulfillment $item) => data_get($item->meta, 'refund.status') === WalletTransaction::STATUS_POSTED
                        );

                    if ($allRefunded && $order->status !== OrderStatus::Refunded) {
                        $order->update(['status' => OrderStatus::Refunded]);
                        $orderRefunded = true;
                    }
                }

                $admin = User::query()->find($adminId);

                activity()
                    ->inLog('payments')
                    ->event('refund.approved')
                    ->performedOn($transaction)
                    ->causedBy($admin)
                    ->withProperties(array_filter([
                        'transaction_id' => $transaction->id,
                        'idempotency_key' => $transaction->idempotency_key,
                        'order_id' => $order->id,
                        'wallet_id' => $wallet->id,
                        'amount' => $transaction->amount,
                        'currency' => $order->currency,
                        'reason' => $approvedReason,
                    ], fn ($value) => $value !== null && $value !== ''))
                    ->log('Refund approved');

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
                        'source' => 'refund',
                    ])
                    ->log('Wallet credited');

                if ($orderRefunded) {
                    activity()
                        ->inLog('orders')
                        ->event('order.refunded')
                        ->performedOn($order)
                        ->causedBy($admin)
                        ->withProperties(array_filter([
                            'order_id' => $order->id,
                            'order_number' => $order->order_number,
                            'status_from' => $orderStatusFrom->value,
                            'status_to' => OrderStatus::Refunded->value,
                            'amount' => $transaction->amount,
                            'currency' => $order->currency,
                            'transaction_id' => $transaction->id,
                        ], fn ($value) => $value !== null && $value !== ''))
                        ->log('Order refunded');
                }

                $userId = $order->user_id;
                $approvedTransactionId = $transaction->id;
                DB::afterCommit(function () use ($userId, $approvedTransactionId): void {
                    dispatch(new EvaluateLoyaltyForUser((int) $userId));
                    $tx = WalletTransaction::query()->find($approvedTransactionId);
                    if ($tx !== null) {
                        $owner = User::query()->find($userId);
                        if ($owner !== null) {
                            $owner->notify(RefundApprovedNotification::fromRefundTransaction($tx));
                        }
                    }
                });

                return $transaction;
            });
        } catch (QueryException $exception) {
            if ($this->isIdempotencyConflict($exception, $idempotencyKey)) {
                $existing = WalletTransaction::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existing !== null) {
                    return $existing;
                }
            }

            throw $exception;
        }
    }

    private function isIdempotencyConflict(QueryException $exception, ?string $idempotencyKey): bool
    {
        if ($idempotencyKey === null) {
            return false;
        }

        $code = (string) $exception->getCode();

        if ($code !== '23000') {
            return false;
        }

        return str_contains($exception->getMessage(), 'idempotency_key');
    }
}
