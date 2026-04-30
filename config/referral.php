<?php

declare(strict_types=1);

return [
    'cookie_name' => env('REFERRAL_COOKIE_NAME', 'karman_ref'),
    /** Last-click attribution cookie lifetime (minutes). */
    'cookie_ttl_minutes' => (int) env('REFERRAL_COOKIE_TTL_MINUTES', 30 * 24 * 60),
];
