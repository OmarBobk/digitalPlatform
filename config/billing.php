<?php

return [
    'currency' => 'USD',
    'currency_symbol' => '$',
    'checkout_fee_fixed' => 0,
    'custom_amount_hard_cap' => (int) env('BILLING_CUSTOM_AMOUNT_HARD_CAP', 100000000),
];
