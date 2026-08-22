# 10. 保護者 購入・ダウンロードAPI

## 対象機能

- 2.4 写真購入
- 2.4 購入済み写真のダウンロード
- 2.4 解除後の購入済みコンテンツへのアクセス継続

## API一覧

| 機能 | Method | Path |
|---|---|---|
| 購入セッション作成 | POST | `/guardian/purchases/checkout-session` |
| 注文一覧 | GET | `/guardian/orders` |
| 購入済み写真一覧 | GET | `/guardian/purchased-photos` |
| ダウンロードURL発行 | POST | `/guardian/photos/{photo_id}/download-url` |

## 1) 購入セッション作成

**Method / Path**: `POST /guardian/purchases/checkout-session`  
**Auth**: `auth:guardian`

### Input

| フィールド | 場所 | 型 | 必須 | バリデーション |
|---|---|---|---|---|
| photo_ids | body | array<string(ULID)> | 必須 | min:1, max:50, 重複不可 |
| checkout_amount | body | integer | 必須 | min:1 |
| success_url | body | string(url) | 必須 | httpsのみ |
| cancel_url | body | string(url) | 必須 | httpsのみ |

### Output（200）

| フィールド | 型 |
|---|---|
| order_id | string(ULID) |
| checkout_session_id | string |
| checkout_url | string(url) |
| total_amount | integer |
| currency | string |

### 認可/制約

- 対象写真は `purchase` 権限が必要（有効紐づけ園児が写っていること）。
- サーバー側で `photo_ids` から最新の販売価格合計を再計算し、`checkout_amount` と一致する場合のみ購入セッションを作成する。
- 価格変更などにより再計算結果と `checkout_amount` が不一致の場合は `409` を返し、フロントに再取得を促す。
- 園の販売可否（Stripe onboarding完了）をサーバーで再判定。
- `orders.platform_fee_amount` は `backend/config/purchase.php` の設定値から算出する。
- 算出式は `round(total_amount * platform_fee_rate)` を基準とし、`platform_fee_min_amount` と `platform_fee_max_amount` で下限・上限を適用する。
- デフォルト値は `platform_fee_rate=0.15`、`platform_fee_min_amount=300`、`platform_fee_max_amount=3000`。
- Stripe Checkout Sessionの有効期限は `backend/config/purchase.php` の `checkout_session_ttl_minutes`（デフォルト30分）で設定し、決済画面を閉じたまま放置された場合の再購入不可期間を24時間からこの時間まで短縮する。
- 同一写真に `pending` の注文が既にある場合、購入セッション作成の都度サーバーがStripe側の最新状態を確認し、その場で解消してから重複判定を行う。
  - Stripeで決済済み（`payment_status=paid`）と判明した場合は entitlement を確定し、その注文を引き続き「購入済み」としてブロックする。
  - Stripeで進行中（`status=open`）と判明した場合は、その場でStripeへ明示的にセッションのキャンセル（`expire`）をリクエストし、注文を `failed` にした上で新しい購入セッションを作成する。
  - 既にStripe側で期限切れ（`status=expired`）の場合も同様に `failed` にしてから新しい購入セッションを作成する。
  - Stripe通信に失敗した場合は安全側に倒し、既存の `pending` 状態のままブロックする（`409 ORDER_ALREADY_PAID_OR_CLOSED`）。
- Webhook到達漏れに備え、`orders:expire-stale-pending` コマンド（`everyFiveMinutes` でスケジュール登録）が `checkout_session_ttl_minutes` に5分の猶予を加えた時間以上前に作成された `pending` 注文をStripe側と定期的に同期する（こちらは強制キャンセルはせず、Stripe側で既に確定した状態のみ反映する）。

## 2) 注文一覧

**Method / Path**: `GET /guardian/orders`  
**Auth**: `auth:guardian`

### Input

| フィールド | 場所 | 型 | 必須 | バリデーション |
|---|---|---|---|---|
| status | query | string | 任意 | `pending` / `paid` / `failed` / `refunded` |
| page | query | integer | 任意 | min:1 |
| per_page | query | integer | 任意 | min:1, max:100 |

### Output（200）

| フィールド | 型 |
|---|---|
| data[].order_id | string(ULID) |
| data[].status | string |
| data[].total_amount | integer |
| data[].created_at | string(datetime) |
| data[].items | array<object> |

## 3) ダウンロードURL発行

**Method / Path**: `POST /guardian/photos/{photo_id}/download-url`  
**Auth**: `auth:guardian`

### Input

| フィールド | 場所 | 型 | 必須 | バリデーション |
|---|---|---|---|---|
| photo_id | path | string(ULID) | 必須 | entitlement存在が必要 |

### Output（200）

| フィールド | 型 |
|---|---|
| download_url | string(url) |
| expires_at | string(datetime) |

### ドメイン制約

- 判定は `entitlements (guardian_id, photo_id)` の存在で行う。
- `guardian_child` が解除済みでも entitlement があればダウンロード許可。

## 4) 購入済み写真一覧

**Method / Path**: `GET /guardian/purchased-photos`  
**Auth**: `auth:guardian`

### Input

| フィールド | 場所 | 型 | 必須 | バリデーション |
|---|---|---|---|---|
| album_id | query | string(ULID) | 任意 | 自分のentitlementで到達可能な写真のアルバムのみ |
| event_date_from | query | string(date) | 任意 | `YYYY-MM-DD` |
| event_date_to | query | string(date) | 任意 | `YYYY-MM-DD`, from以上 |
| page | query | integer | 任意 | min:1 |
| per_page | query | integer | 任意 | min:1, max:100 |

### Output（200）

| フィールド | 型 |
|---|---|
| data[].photo_id | string(ULID) |
| data[].album_id | string(ULID) |
| data[].downloadable | boolean |
| data[].purchased_at | string(datetime) |
| data[].event_date | string(date) |
| data[].preview_url | string(url) |
| meta.current_page | integer |
| meta.total | integer |

### 認可/制約

- 判定は `entitlements (guardian_id, photo_id)` を正とし、`guardian_child` の有効/解除状態に依存しない。
- `downloadable` はentitlementが有効な写真で常に `true` とする（返却対象をentitlement保有写真に限定するため）。
- `preview_url` は一覧表示用途の短期URLとし、原本ダウンロードは `POST /guardian/photos/{photo_id}/download-url` で都度発行する。

## 共通エラー

| HTTP | code | 条件 |
|---|---|---|
| 401 | GUARDIAN_AUTH_REQUIRED | 未認証 |
| 403 | PHOTO_PURCHASE_NOT_ALLOWED | 非可視写真の購入 |
| 403 | SALES_DISABLED_FOR_KINDERGARTEN | 園の販売停止中 |
| 404 | ENTITLEMENT_NOT_FOUND | 未購入 |
| 409 | CHECKOUT_AMOUNT_MISMATCH | 最新価格合計と入力金額が不一致 |
| 409 | ORDER_ALREADY_PAID_OR_CLOSED | 注文状態不整合 |
| 422 | VALIDATION_ERROR | 入力不正 |
