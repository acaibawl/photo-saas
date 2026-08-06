# 08. 保護者 紐づけAPI

## 対象機能

- 2.2 兄弟姉妹の追加紐づけ
- 2.2 複数園児の一元管理

## API一覧

| 機能 | Method | Path |
|---|---|---|
| 招待受諾（ログイン済み追加紐づけ） | POST | `/guardian/invitations/{raw_token}/accept` |
| 紐づけ園児一覧 | GET | `/guardian/children` |

## 1) 追加紐づけ

**Method / Path**: `POST /guardian/invitations/{raw_token}/accept`  
**Auth**: `auth:guardian`

### Input

| フィールド | 場所 | 型 | 必須 | バリデーション |
|---|---|---|---|---|
| raw_token | path | string | 必須 | 有効招待であること |

### Output（200）

| フィールド | 型 |
|---|---|
| guardian_id | string(ULID) |
| linked_child.id | string(ULID) |
| linked_child.kindergarten_id | string(ULID) |
| linked_at | string(datetime) |

### ドメイン制約

- 同一 `(guardian_id, child_id)` に有効行がある場合は重複作成せず `409`。
- 招待消費は `guardian_child` 追加と同一トランザクションで実施。
- `raw_token` が指す招待行は `SELECT ... FOR UPDATE` でロックし、同一トランザクション内で `used_at` / `revoked_at` / `expires_at` を再検証してから消費する。
- `used_at` は `409 INVITATION_ALREADY_USED` として扱い、`revoked_at` / `expires_at` は従来どおり `403 INVITATION_INVALID_OR_EXPIRED` のまま維持する。
- 条件付き消費更新を採用する場合は、`token_hash` に対する一意制約を前提に `used_at IS NULL` かつ `revoked_at IS NULL` かつ `expires_at > now()` を満たす場合のみ更新し、更新件数0件なら再読込して状態別に 409/403 を返す。

## 2) 紐づけ園児一覧（現在有効な紐づけのみ）

**Method / Path**: `GET /guardian/children`  
**Auth**: `auth:guardian`

### Input

なし

### Output（200）

現在有効な紐づけのみを返す。解除済み・過去の紐づけは含めない。

| フィールド | 型 |
|---|---|
| data[].child_id | string(ULID) |
| data[].child_name | string |
| data[].class_name | string |
| data[].kindergarten_id | string(ULID) |
| data[].kindergarten_name | string |
| data[].label | string |
| data[].linked_at | string(datetime) |

## 共通エラー

| HTTP | code | 条件 |
|---|---|---|
| 401 | GUARDIAN_AUTH_REQUIRED | 未認証 |
| 403 | INVITATION_INVALID_OR_EXPIRED | `revoked_at` / `expires_at` による招待無効 |
| 409 | INVITATION_ALREADY_USED | `used_at` による使用済み招待 |
| 409 | GUARDIAN_CHILD_LINK_ALREADY_EXISTS | 重複紐づけ |
| 422 | VALIDATION_ERROR | 入力不正 |
