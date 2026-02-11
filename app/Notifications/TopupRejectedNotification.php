<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\TopupRequest;
use Illuminate\Support\Facades\Route;

class TopupRejectedNotification extends BaseNotification
{
    public static function fromTopupRequest(TopupRequest $topupRequest, ?string $reason = null): self
    {
        $amount = number_format((float) $topupRequest->amount, 2);
        $currency = $topupRequest->currency ?? 'USD';

        return new self(
            sourceType: TopupRequest::class,
            sourceId: $topupRequest->id,
            title: __('notifications.topup_rejected_title'),
            message: __('notifications.topup_rejected_message', [
                'amount' => $amount,
                'currency' => $currency,
                'reason' => $reason ?? __('notifications.no_reason_given'),
            ]),
            url: Route::has('wallet') ? route('wallet') : null
        );
    }
}
