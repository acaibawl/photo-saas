## Purchase Fee Settings

Guardian purchase APIs compute `platform_fee_amount` from config rather than hard-coding it.

- `PURCHASE_PLATFORM_FEE_RATE`: fee rate, default `0.15`
- `PURCHASE_PLATFORM_FEE_MIN_AMOUNT`: minimum fee amount, default `300`
- `PURCHASE_PLATFORM_FEE_MAX_AMOUNT`: maximum fee amount, default `3000`

The service uses `round(total_amount * rate)` first, then clamps the result between the configured minimum and maximum.
