<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Settlement created notification
    |--------------------------------------------------------------------------
    |
    | When true, admin users receive a notification when a settlement batch
    | is created (profit:settle command). When false, no notification is sent.
    |
    */
    'settlement_created_enabled' => env('NOTIFY_ON_SETTLEMENT_CREATED', false),

];
