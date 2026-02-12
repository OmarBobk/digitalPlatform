<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Wallet velocity
    |--------------------------------------------------------------------------
    | Alert when this many POSTED wallet transactions occur within the window
    | (same wallet). Idempotency uses a time bucket of window_seconds.
    */
    'wallet_velocity' => [
        'threshold' => (int) env('OI_WALLET_VELOCITY_THRESHOLD', 3),
        'window_seconds' => (int) env('OI_WALLET_VELOCITY_WINDOW_SECONDS', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Refund abuse
    |--------------------------------------------------------------------------
    | Alert when this many refund.approved (non-financial) events occur for
    | the same user within the window. Idempotency uses a 10-minute bucket.
    */
    'refund_abuse' => [
        'threshold' => (int) env('OI_REFUND_ABUSE_THRESHOLD', 5),
        'window_minutes' => (int) env('OI_REFUND_ABUSE_WINDOW_MINUTES', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Fulfillment failure spike
    |--------------------------------------------------------------------------
    | Alert when this many failed fulfillments occur (same provider or same
    | product) within the window. Idempotency uses a time bucket.
    */
    'fulfillment_failure' => [
        'threshold' => (int) env('OI_FULFILLMENT_FAILURE_THRESHOLD', 5),
        'window_minutes' => (int) env('OI_FULFILLMENT_FAILURE_WINDOW_MINUTES', 30),
    ],

];
