<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Actions\Fulfillments\CreateFulfillmentsForOrder;
use App\Enums\OrderStatus;
use App\Enums\WalletTransactionDirection;
use App\Enums\WalletTransactionType;
use App\Models\Order;
use App\Models\Wallet;
use App\Models\WalletTransaction;
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

            $existingTransaction = WalletTransaction::query()
                ->where('reference_type', Order::class)
                ->where('reference_id', $lockedOrder->id)
                ->lockForUpdate()
                ->first();

            if ($existingTransaction !== null && $existingTransaction->status === WalletTransaction::STATUS_POSTED) {
                $lockedOrder->fill([
                    'status' => OrderStatus::Paid,
                    'paid_at' => $lockedOrder->paid_at ?? now(),
                ])->save();

                (new CreateFulfillmentsForOrder)->handle($lockedOrder);

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
                    'meta' => [
                        'order_number' => $lockedOrder->order_number,
                    ],
                ]);
            } elseif ($existingTransaction->status === WalletTransaction::STATUS_PENDING) {
                $existingTransaction->status = WalletTransaction::STATUS_POSTED;
                $existingTransaction->save();
            }

            $lockedWallet->decrement('balance', $lockedOrder->total);

            $lockedOrder->fill([
                'status' => OrderStatus::Paid,
                'paid_at' => now(),
            ])->save();

            (new CreateFulfillmentsForOrder)->handle($lockedOrder);

            return $lockedOrder;
        };

        return $useTransaction
            ? DB::transaction($operation)
            : $operation();
    }
}
