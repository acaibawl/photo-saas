<?php

return [
    'platform_fee_rate' => env('PURCHASE_PLATFORM_FEE_RATE', 0.15),
    'platform_fee_min_amount' => env('PURCHASE_PLATFORM_FEE_MIN_AMOUNT', 300),
    'platform_fee_max_amount' => env('PURCHASE_PLATFORM_FEE_MAX_AMOUNT', 3000),
];
