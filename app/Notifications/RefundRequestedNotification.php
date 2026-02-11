<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\WalletTransaction;
use Illuminate\Support\Facades\Route;

class RefundRequestedNotification extends BaseNotification
{
    public static function fromRefundTransaction(WalletTransaction $transaction): self
    {
        $amount = number_format((float) $transaction->amount, 2);
        $orderId = (int) data_get($transaction->meta, 'order_id', 0);

        return new self(
            sourceType: WalletTransaction::class,
            sourceId: $transaction->id,
            title: __('notifications.refund_requested_title'),
            message: __('notifications.refund_requested_message', [
                'amount' => $amount,
                'transaction_id' => $transaction->id,
            ]),
            url: Route::has('refunds') ? route('refunds') : null
        );
    }
}
