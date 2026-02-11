<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Settlement;
use Illuminate\Support\Facades\Route;

class SettlementCreatedNotification extends BaseNotification
{
    public static function fromSettlement(Settlement $settlement): self
    {
        $amount = number_format((float) $settlement->total_amount, 2);
        $count = $settlement->fulfillments()->count();

        return new self(
            sourceType: Settlement::class,
            sourceId: $settlement->id,
            title: __('notifications.settlement_created_title'),
            message: __('notifications.settlement_created_message', [
                'settlement_id' => $settlement->id,
                'amount' => $amount,
                'count' => $count,
            ]),
            url: Route::has('settlements') ? route('settlements') : null
        );
    }
}
