<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\TopupRequest;
use Illuminate\Support\Facades\Route;

class TopupRequestedNotification extends BaseNotification
{
    public static function fromTopupRequest(TopupRequest $topupRequest): self
    {
        $amount = number_format((float) $topupRequest->amount, 2);
        $currency = $topupRequest->currency ?? 'USD';
        $amountDisplay = strtoupper($currency) === 'USD'
            ? config('billing.currency_symbol', '$').$amount
            : $amount.' '.$currency;

        return new self(
            sourceType: TopupRequest::class,
            sourceId: $topupRequest->id,
            title: __('notifications.topup_requested_title'),
            message: __('notifications.topup_requested_message', [
                'amount_display' => $amountDisplay,
                'id' => $topupRequest->id,
            ]),
            url: Route::has('topups') ? route('topups') : null
        );
    }
}
