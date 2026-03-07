<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Firebase / FCM Server Credentials (HTTP v1 API)
    |--------------------------------------------------------------------------
    | Used for server-side authentication when sending push notifications.
    | Private key may be base64-encoded or contain literal \n; we normalize.
    */

    'project_id' => env('FIREBASE_PROJECT_ID'),

    'private_key' => env('FIREBASE_PRIVATE_KEY'),

    'client_email' => env('FIREBASE_CLIENT_EMAIL'),

];
