# 06. 園側 Stripe Connect API

## 対象機能

- 1.5 Stripe Connectオンボーディング
- 1.5 オンボーディング状況に応じた販売制御

## API一覧

| 機能 | Method | Path |
|---|---|---|
| オンボーディング開始URL発行 | POST | `/staff/stripe/connect/onboarding-link` |
| 接続状況取得 | GET | `/staff/stripe/connect/status` |
| 販売可否判定取得 | GET | `/staff/sales/availability` |

## 1) オンボーディング開始URL発行

**Method / Path**: `POST /staff/stripe/connect/onboarding-link`  
**Auth**: `auth:staff` + `role:owner`

### Input

| フィールド | 場所 | 型 | 必須 | バリデーション |
|---|---|---|---|---|
| return_url | body | string(url) | 必須 | httpsのみ |
| refresh_url | body | string(url) | 必須 | httpsのみ |

### Output（200）

| フィールド | 型 |
|---|---|
| onboarding_url | string(url) |
| stripe_account_id | string |
| expires_at | string(datetime) |

### ドメイン制約

- `owner` のみが実行可能。

## 2) 接続状況取得

**Method / Path**: `GET /staff/stripe/connect/status`  
**Auth**: `auth:staff` + `role:owner`

### Input

なし

### Output（200）

| フィールド | 型 | 説明 |
|---|---|---|
| stripe_account_id | string or null | 未作成時null |
| charges_enabled | boolean | Stripe状態 |
| payouts_enabled | boolean | Stripe状態 |
| onboarding_completed_at | string(datetime or null) | 完了日時 |
| requirements_due | array<string> | 未充足要件 |

### ドメイン制約

- `owner` のみが実行可能。

## 3) 販売可否判定取得

**Method / Path**: `GET /staff/sales/availability`  
**Auth**: `auth:staff` + `role:owner`

### Input

なし

### Output（200）

| フィールド | 型 |
|---|---|
| sales_enabled | boolean |
| reason_code | string or null |
| reason_message | string or null |

### 判定ルール

- `stripe_onboarding_completed_at` が未設定、または `charges_enabled=false` の場合 `sales_enabled=false`。
- 保護者向け購入APIでも同条件を強制し、フロント制御のみには依存しない。

### ドメイン制約

- `owner` のみが実行可能。

## 共通エラー

| HTTP | code | 条件 |
|---|---|---|
| 401 | STAFF_AUTH_REQUIRED | 未認証 |
| 403 | STAFF_ROLE_FORBIDDEN | `owner` 以外による実行 |
| 502 | STRIPE_API_ERROR | Stripe連携失敗 |
| 422 | VALIDATION_ERROR | 入力不正 |
