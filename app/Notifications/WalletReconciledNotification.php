<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Wallet;
use Illuminate\Support\Facades\Route;

class WalletReconciledNotification extends BaseNotification
{
    public static function fromWallet(Wallet $wallet, float $storedBalance, float $expectedBalance, float $diff): self
    {
        return new self(
            sourceType: Wallet::class,
            sourceId: $wallet->id,
            title: __('notifications.wallet_reconciled_title'),
            message: __('notifications.wallet_reconciled_message', [
                'wallet_id' => $wallet->id,
                'user_id' => $wallet->user_id,
                'stored' => number_format($storedBalance, 2),
                'expected' => number_format($expectedBalance, 2),
                'diff' => number_format($diff, 2),
            ]),
            url: Route::has('customer-funds') ? route('customer-funds') : null
        );
    }
}
