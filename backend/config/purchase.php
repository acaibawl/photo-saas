<?php

$platformFeeMinAmount = (int) env('PURCHASE_PLATFORM_FEE_MIN_AMOUNT', 300);
$platformFeeMaxAmount = (int) env('PURCHASE_PLATFORM_FEE_MAX_AMOUNT', 3000);

// Stripeは30分〜24時間(1440分)の範囲のみ許容するため、設定読込時に丸めておく
$checkoutSessionTtlMinutes = (int) env('PURCHASE_CHECKOUT_SESSION_TTL_MINUTES', 30);
$checkoutSessionTtlMinutes = min(max($checkoutSessionTtlMinutes, 30), 1440);

return [
    'platform_fee_rate' => env('PURCHASE_PLATFORM_FEE_RATE', 0.15),
    'platform_fee_min_amount' => min($platformFeeMinAmount, $platformFeeMaxAmount),
    'platform_fee_max_amount' => max($platformFeeMinAmount, $platformFeeMaxAmount),
    // Stripe Checkout Sessionの有効期限（分）。決済画面を閉じたまま放置された場合の再購入不可期間を短縮する
    'checkout_session_ttl_minutes' => $checkoutSessionTtlMinutes,
];
