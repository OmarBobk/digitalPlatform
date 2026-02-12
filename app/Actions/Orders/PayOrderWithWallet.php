<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Actions\Fulfillments\CreateFulfillmentsForOrder;
use App\Enums\OrderStatus;
use App\Enums\WalletTransactionDirection;
use App\Enums\WalletTransactionType;
use App\Models\Order;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\OperationalIntelligenceService;
use App\Services\SystemEventService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayOrderWithWallet
{
    /**
     * Debit the wallet only after posting a ledger transaction.
     */
    public function handle(Order $order, Wallet $wallet, bool $useTransaction = true): Order
    {
        $operation = function () use ($order, $wallet): Order {
            $lockedOrder = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrder->status === OrderStatus::Paid) {
                return $lockedOrder;
            }

            if ($lockedOrder->status !== OrderStatus::PendingPayment) {
                return $lockedOrder;
            }

            if ($lockedOrder->user_id !== $wallet->user_id) {
                throw ValidationException::withMessages([
                    'wallet' => 'Wallet does not belong to order owner.',
                ]);
            }

            $lockedWallet = Wallet::query()
                ->whereKey($wallet->id)
                ->lockForUpdate()
                ->firstOrFail();

            $idempotencyKey = 'purchase:order:'.$lockedOrder->id;
            $existingTransaction = WalletTransaction::query()
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existingTransaction === null) {
                $existingTransaction = WalletTransaction::query()
                    ->where('reference_type', Order::class)
                    ->where('reference_id', $lockedOrder->id)
                    ->lockForUpdate()
                    ->first();
            }

            if ($existingTransaction !== null && $existingTransaction->status === WalletTransaction::STATUS_POSTED) {
                $lockedOrder->fill([
                    'status' => OrderStatus::Paid,
                    'paid_at' => $lockedOrder->paid_at ?? now(),
                ])->save();

                (new CreateFulfillmentsForOrder)->handle($lockedOrder);

                $this->logOrderPaid($lockedOrder, $lockedWallet, $existingTransaction);

                return $lockedOrder;
            }

            if ((float) $lockedWallet->balance < (float) $lockedOrder->total) {
                throw ValidationException::withMessages([
                    'wallet' => 'Insufficient wallet balance.',
                ]);
            }

            if ($existingTransaction === null) {
                $existingTransaction = WalletTransaction::create([
                    'wallet_id' => $lockedWallet->id,
                    'type' => WalletTransactionType::Purchase,
                    'direction' => WalletTransactionDirection::Debit,
                    'amount' => $lockedOrder->total,
                    'status' => WalletTransaction::STATUS_POSTED,
                    'reference_type' => Order::class,
                    'reference_id' => $lockedOrder->id,
                    'idempotency_key' => $idempotencyKey,
                    'meta' => [
                        'order_number' => $lockedOrder->order_number,
                    ],
                ]);
            } elseif ($existingTransaction->status === WalletTransaction::STATUS_PENDING) {
                $existingTransaction->status = WalletTransaction::STATUS_POSTED;
                $existingTransaction->idempotency_key = $idempotencyKey;
                $existingTransaction->save();
            }

            $lockedWallet->decrement('balance', $lockedOrder->total);

            $lockedOrder->fill([
                'status' => OrderStatus::Paid,
                'paid_at' => now(),
            ])->save();

            (new CreateFulfillmentsForOrder)->handle($lockedOrder);

            $this->logOrderPaid($lockedOrder, $lockedWallet, $existingTransaction);

            $orderUser = User::query()->find($lockedOrder->user_id);
            app(SystemEventService::class)->record(
                'wallet.purchase.debited',
                $lockedOrder,
                $orderUser,
                [
                    'amount' => (float) $lockedOrder->total,
                    'wallet_id' => $lockedWallet->id,
                    'transaction_id' => $existingTransaction->id,
                ],
                'info',
                true,
            );

            $postedTxId = $existingTransaction->id;
            DB::afterCommit(function () use ($postedTxId): void {
                $tx = WalletTransaction::query()->find($postedTxId);
                if ($tx !== null) {
                    app(OperationalIntelligenceService::class)->detectWalletVelocity($tx);
                }
            });

            return $lockedOrder;
        };

        return $useTransaction
            ? DB::transaction($operation)
            : $operation();
    }

    private function logOrderPaid(Order $order, Wallet $wallet, WalletTransaction $transaction): void
    {
        $causer = User::query()->find($order->user_id);

        activity()
            ->inLog('orders')
            ->event('order.paid')
            ->performedOn($order)
            ->causedBy($causer)
            ->withProperties([
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'amount' => $order->total,
                'currency' => $order->currency,
                'status_from' => OrderStatus::PendingPayment->value,
                'status_to' => OrderStatus::Paid->value,
                'transaction_id' => $transaction->id,
                'wallet_id' => $wallet->id,
            ])
            ->log('Order paid');

        activity()
            ->inLog('payments')
            ->event('wallet.debited')
            ->performedOn($transaction)
            ->causedBy($causer)
            ->withProperties([
                'wallet_id' => $wallet->id,
                'order_id' => $order->id,
                'transaction_id' => $transaction->id,
                'amount' => $transaction->amount,
                'currency' => $order->currency,
                'direction' => WalletTransactionDirection::Debit->value,
            ])
            ->log('Wallet debited');
    }
}
