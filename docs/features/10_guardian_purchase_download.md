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
