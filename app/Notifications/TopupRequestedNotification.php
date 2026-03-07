<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\TopupRequest;
use Illuminate\Support\Facades\Route;

class TopupRequestedNotification extends BaseNotification
{
    public function via(object $notifiable): array
    {
        $channels = ['database'];
        if (config('broadcasting.default') !== 'null') {
            $channels[] = 'broadcast';
        }
        $channels[] = 'fcm';

        return $channels;
    }

    /**
     * @return array{title: string, body: string, sound: string, url: string}
     */
    public function toFcm(object $notifiable): array
    {
        $path = Route::has('topups') ? parse_url(route('topups'), PHP_URL_PATH) : '/topups';

        return [
            'title' => __('notifications.topup_requested_title'),
            'body' => $this->message,
            'sound' => '/sounds/topup.mp3',
            'url' => $path ?: '/topups',
        ];
    }

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
