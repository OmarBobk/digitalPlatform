<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\TopupRequest;
use Illuminate\Support\Facades\Route;

class TopupApprovedNotification extends BaseNotification
{
    public static function fromTopupRequest(TopupRequest $topupRequest): self
    {
        $amount = number_format((float) $topupRequest->amount, 2);
        $currency = $topupRequest->currency ?? 'USD';

        return new self(
            sourceType: TopupRequest::class,
            sourceId: $topupRequest->id,
            title: __('notifications.topup_approved_title'),
            message: __('notifications.topup_approved_message', [
                'amount' => $amount,
                'currency' => $currency,
            ]),
            url: Route::has('wallet') ? route('wallet') : null
        );
    }
}
